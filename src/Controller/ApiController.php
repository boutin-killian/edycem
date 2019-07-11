<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Job;
use App\Entity\Project;
use App\Entity\Settings;
use App\Entity\Task;
use App\Entity\WorkingTime;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JMS\Serializer\SerializerBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use FOS\RestBundle\Controller\Annotations\Get;
use FOS\RestBundle\Controller\Annotations\View;
use FOS\RestBundle\Controller\Annotations\Post;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\HttpFoundation\Request;

class ApiController extends AbstractController
{

    private $em;
    private $canAccessApi = false;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * @Post(
     *     path = "/api/{routeName}",
     *     name = "entity_post"
     * )
     */
    public function postAction($routeName, Request $request)
    {
        if ($request->query->get('access_token') !== null) {
            $tokenApi = $request->query->get('access_token');
            if ($this->canAccessApi($tokenApi)) {
                $apiConfig = $this->getApiConfig($routeName, 'showOne');
                $entityPath = "App\\Entity\\" . $apiConfig['name'];
                $object = new $entityPath();

                // Insert new Working Time
                if ($apiConfig['name'] === 'WorkingTime') {
                    $post = (array) json_decode(file_get_contents('php://input'));
                    $object = new WorkingTime();
                    $object->setUser(isset($post['user_id']) ? $this->getDoctrine()->getRepository('App\Entity\User')->findOneBy(['id' => intval($post['user_id'])]) : '');
                    $object->setProject(isset($post['project_id']) ? $this->getDoctrine()->getRepository('App\Entity\Project')->findOneBy(['id' => intval($post['project_id'])]) : '');
                    $object->setTask(isset($post['task_id']) ? $this->getDoctrine()->getRepository('App\Entity\Task')->findOneBy(['id' => intval($post['task_id'])]) : '');
                    $object->setDate(new \DateTime(isset($post['date']) ? $post['date'] : ''));
                    $object->setSpentTime(isset($post['spent_time']) ? $post['spent_time'] : 0);
                    $object->setDescription(isset($post['description']) ? $post['description'] : '');

                    $this->em->persist($object);
                    $this->em->flush();
                }

                // Update User RGPD
                if ($apiConfig['name'] === 'User') {
                    $post = (array) json_decode(file_get_contents('php://input'));
                    $user = $this->getDoctrine()->getRepository('App\Entity\User')->findOneBy(['id' => $post['id']]);
                    $object = clone $user;

                    if (isset($post['date_rgpd'])) {
                        $object->setDateRgpd(new \DateTime($post['date_rgpd']));
                    };

                    $this->em->merge($object);
                    $this->em->flush();
                }

                return new JsonResponse($object->toJSON());
            } else {
                return new JsonResponse('Token d\'accès à l\'API invalide');
            }
        } else {
            return new JsonResponse('Aucun Token d\'accès à l\'API');
        }
    }

    /**
     * @Get(
     *     path = "/api/{routeName}",
     *     name = "entity_get",
     * )
     * @View()
     */
    public function getAction($routeName, Request $request)
    {
        // S'il y a un paramètre à la requête
        if (sizeof($request->query) > 0 && $request->query->get('email') !== null) {
            $apiConfig = $this->getApiConfig($routeName, 'showOne');
        } else {
            $apiConfig = $this->getApiConfig($routeName, 'showAll');
        }

        if ($apiConfig !== false) {
            $entityPath = "App\\Entity\\" . $apiConfig['name'];
            $fields = isset($apiConfig['showAllFields']) ? $apiConfig['showAllFields'] : $apiConfig['showOne'];
            $where = isset($apiConfig['showAllCondition']) ? $apiConfig['showAllCondition'] : '1 = 1';

            // Si il est demandé de chercher un utilisateur par son email, on change le token et on renvoie l'utilisateur
            if ($request->query->get('email') !== null) {

                $email = $request->query->get('email');
                $entityObject = $this->getDoctrine()->getRepository($entityPath)->findOneBy(['email' => $email]);

                // Change ApiToken
                $entityObject->setApiToken(bin2hex(openssl_random_pseudo_bytes(64)));
                $this->em->persist($entityObject);
                $this->em->flush();

                // Show user with new ApiToken
                return $this->serializeContent($entityObject);
            } else { // On regarde si le token d'accès est valide, si oui, on fait le requête GET correspondante
                if ($request->query->get('access_token') !== null) {
                    $tokenApi = $request->query->get('access_token');
                    if ($this->canAccessApi($tokenApi)) {

                        if ($request->query->get('action') && $request->query->get('action') == 'lastProjects') {

                            if ($request->query->get('id')) {

                                // Get last project of user
                                $lastProjectUser = $this->getDoctrine()->getRepository('App\Entity\Project')->getLastProject($request->query->get('id'));
                                if (empty($lastProjectUser)) {$lastProjectUser[0]['id'] = 0;}
                                $lastProjectUser[0]['tag'] = 'last';

                                // Get last project created
                                $lastProjectCreated = $this->getDoctrine()->getRepository('App\Entity\Project')->getLastCreatedProject();
                                if (empty($lastProjectCreated)) {$lastProjectCreated[0]['id'] = 0;}
                                $lastProjectCreated[0]['tag'] = 'new';

                                // Get 8 most used projects order by most used (last month)
                                $allProjectsLastMonth = $this->getDoctrine()->getRepository('App\Entity\Project')->findByWithFields();
                                if (empty($allProjectsLastMonth)) {$allProjectsLastMonth[0]['id'] = 0;}

                                $whereMostUsed = implode(',', array_map(function($value){return $value['id']; }, $allProjectsLastMonth));
                                $mostUsedProject = $this->getDoctrine()->getRepository('App\Entity\WorkingTime')->getMostUsed($whereMostUsed);
                                $mostUsedProject = array_map(function ($value) {
                                    $value['id'] = intval($value['id']);
                                    $value['tag'] = 'most used';
                                    return $value;
                                }, $mostUsedProject);

                                $entityObject = array_merge($lastProjectUser, $lastProjectCreated, $mostUsedProject);
                            } else {
                                return new JsonResponse('Aucun paramètre "id" correspondant à l\'id utilisateur trouvé');
                            }

                        } else {
                            $entityObject = $this->getDoctrine()->getRepository($entityPath)->findAllWithFields($fields, $where);
                        }
                        return $this->serializeContent($entityObject);
                    } else {
                        return new JsonResponse('Token d\'accès à l\'API invalide');
                    }
                } else {
                    return new JsonResponse('Aucun Token d\'accès à l\'API');
                }
            }
        }

        throw new NotFoundHttpException();
    }

    public
    function getApiConfig($routeName, $routeType)
    {
        $apiConfig = Yaml::parseFile('../config/api.yaml');

        foreach ($apiConfig['api']['entities'] as $entity) {
            if (isset($entity[$routeType])) {
                if ($entity[$routeType] == $routeName) {
                    return $entity;
                }
            } else {
                // Route name of type is not defined in config
                throw new NotFoundHttpException($routeType . ' has to be defined in config/api.yaml');
            }
        }
        // API not defined for this entity
        return false;
    }

    public
    function serializeContent($entityObject)
    {
        $serializer = SerializerBuilder::create()->build();

        $data = $serializer->serialize($entityObject, 'json');

        $response = new Response($data);
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /* Check if the API Token is valid */
    public
    function canAccessApi($token)
    {
        if ($token) {
            $user = $this->em->getRepository(User::class)
                ->findOneBy(['apiToken' => $token]);

            if ($user !== null) {
                return $this->canAccessApi = true;
            }
        }
        return $this->canAccessApi = false;
    }
}