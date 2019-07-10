<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Entity\Job;
use App\Exporter\NormalizerConfigPass;
use App\Exporter\PropertyConfigPass;
use App\Exporter\TemplateConfigPass;
use EasyCorp\Bundle\EasyAdminBundle\Search\Paginator;
use EasyCorp\Bundle\EasyAdminBundle\Twig\EasyAdminTwigExtension;
use FOS\UserBundle\Model\UserManagerInterface;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use EasyCorp\Bundle\EasyAdminBundle\Controller\EasyAdminController as BaseAdminController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Role\Role;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Translation\TranslatorInterface;

class AdminController extends BaseAdminController
{
    private $logger;
    private $roleHierarchy;
    private $translator;
    private $normalizerConfigPass;
    private $propertyConfigPass;
    private $templateConfigPass;
    private $userManager;

    public function __construct(LoggerInterface $logger, RoleHierarchyInterface $roleHierarchy, TranslatorInterface $translator, NormalizerConfigPass $normalizerConfigPass, PropertyConfigPass $propertyConfigPass, TemplateConfigPass $templateConfigPass,  UserManagerInterface $userManager)
    {
        $this->logger = $logger;
        $this->roleHierarchy = $roleHierarchy;
        $this->translator = $translator;
        $this->normalizerConfigPass = $normalizerConfigPass;
        $this->propertyConfigPass = $propertyConfigPass;
        $this->templateConfigPass = $templateConfigPass;
        $this->userManager = $userManager;
    }

    /**
     * @Route("/dashboard", name="admin_dashboard")
     */
    public function dashboardAction()
    {
        $users = $this->getDoctrine()
            ->getRepository(User::class)
            ->getCountUsers();

        $jobs = $this->getDoctrine()
            ->getRepository(Job::class)
            ->getCountJobs();


        $views = array(
            'User' => array(
                'name' => 'users',
                'icon' => 'user',
                'number' => $users,
                'menuIndex' => '6',
                'submenuIndex' => '0'
            ),
            'Job' => array(
                'name' => 'jobs',
                'icon' => '',
                'number' => $jobs,
                'menuIndex' => '2',
                'submenuIndex' => '0'
            ),
        );

        return $this->render('admin/dashboard.html.twig', array(
            'views' => $views
        ));
    }

    /**
     * export action.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAction()
    {
        $entityName = $this->entity['name'];
        $user = $this->getUser();
        $userRoles = $user->getRoles();

        // Get hierarchical role of user to know if he can export
        $accessDenied = true;
        if (array_key_exists('export', $this->config['entities'][$entityName])) {
            foreach ($userRoles as $userRole) {
                $role = new Role($userRole);
                $all_roles = $this->roleHierarchy->getReachableRoles(array($role));
                foreach ($all_roles as $role) {
                    if (isset($this->config['entities'][$entityName]['export']['role'])) {
                        if ($this->config['entities'][$entityName]['export']['role'] == $role->getRole()) {
                            $accessDenied = false;
                        }
                    } else {
                        if ($this->config['entities'][$entityName]['role'] == $role->getRole()) {
                            $accessDenied = false;
                        }
                    }
                }
            }
        }

        // property/normalize/template config pass on all export fields
        $this->config = $this->normalizerConfigPass->process($this->config);
        $this->config = $this->propertyConfigPass->process($this->config);
        $this->config = $this->templateConfigPass->process($this->config);

        if (!$accessDenied) {
            // if "no key export" or "empty export array" or "no key fields in array export" or "empty fields array in export array" => export all properties
            if (!array_key_exists('export', $this->config['entities'][$entityName]) ||
                empty($this->config['entities'][$entityName]['export']) ||
                !array_key_exists('fields', $this->config['entities'][$entityName]['export']) ||
                empty($this->config['entities'][$entityName]['export']['fields'])) {
                $this->config['entities'][$entityName]['export']['fields'] = $this->config['entities'][$entityName]['properties'];
            }

            $this->setExportData();

            $searchableFields = $this->entity['search']['fields'];
            $paginator = $this->findBy($this->entity['class'],
                $this->request->query->get('query'), $searchableFields, 1, PHP_INT_MAX,
                $this->request->query->get('sortField'),
                $this->request->query->get('sortDirection'),
                $this->entity['search']['dql_filter']);

        } else {
            throw new AccessDeniedException('Vous n\'avez pas les droits d\'accès à cette page.');
        }

        return $this->getExportFile($paginator, $this->config['entities'][$entityName]['export']['fields']);

    }

    /**
     * Format CSV file
     *
     * @param Paginator $paginator recordsets to export
     * @param array $fields fields to display
     * @return Response
     */
    public function getExportFile($paginator, $fields)
    {
        $handle = fopen('php://memory', 'r+');

        // First line
        $keys = array_keys($fields);
        for ($i = 0; $i < count($keys); $i++) {
            $keys[$i] = isset($fields[$keys[$i]]['label']) ? $this->translator->trans($fields[$keys[$i]]['label']) : $this->translator->trans($keys[$i]);
        }
        fputcsv($handle, $keys, ';', '"');

        $twig = $this->get('twig');
        $ea_twig = $twig->getExtension(EasyAdminTwigExtension::class);

        foreach ($paginator as $entity) {
            $row = [];
            foreach ($fields as $field) {

                switch ($field['property']) {
                    case 'task':
                        $value = '';
                        break;
                    default:
                        $value = $ea_twig->renderEntityField($twig, 'list', $this->entity['name'], $entity, $field);
                }

                $row[] = trim($value);
            }
            fputcsv($handle, $row, ';', '"');
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return new Response("\xEF\xBB\xBF" . $content, 200, array(
            'Content-Type' => 'application/force-download',
            'Content-Disposition' => 'attachment; filename="' . sprintf('export-%s-%s.csv', strtolower($this->entity['name']), date('Ymd_His')) . '"'
        ));
    }

    /**
     * Setting specific data to export
     * Example :  dynamic dql_filter etc ...
     */
    public function setExportData()
    {
        // Don't retrieve specific data in this project
    }

    public function createNewUserEntity()
    {
        return $this->userManager->createUser();
    }

    public function persistUserEntity($user)
    {
        $user->setEnabled(true);
        $this->userManager->updateUser($user, false);
        parent::persistEntity($user);
    }

    public function updateUserEntity($user)
    {
        $this->userManager->updateUser($user, false);
        parent::updateEntity($user);
    }
}
