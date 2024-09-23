<?php

namespace App\Controller;

use App\Entity\Advice;
use App\Repository\AdviceRepository;
use App\Repository\MonthRepository;
use App\Service\WeatherService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Response;

#[Route('/api')]
class MeteoController extends AbstractController
{
    private $httpClient;
    private $cache;

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    #[Route('/conseil', name: 'app_advice', methods: ['GET'])]
    public function AdviceThisMonth(AdviceRepository $adviceRepository): JsonResponse
    {
        // Obtenir le mois actuel (1 à 12)
        $currentMonthNumber = (int)(new \DateTime())->format('n');

        // Récupérer les conseils pour le mois courant via le repository
        $adviceList = $adviceRepository->findAdviceByMonthNumber($currentMonthNumber);

        // Préparer la liste des conseils
        $adviceContent = array_map(fn($advice) => $advice->getContent(), $adviceList);

        return $this->json([
            'month' => $currentMonthNumber,
            'advice' => $adviceContent,
        ]);
    }

    #[Route('/conseil/{month}', name: 'app_advice_month', methods: ['GET'])]
    public function AdviceByMonth($month, AdviceRepository $adviceRepository): JsonResponse
    {
        // Obtenir le mois actuel (1 à 12)
        $currentMonthNumber = $month;

        // Récupérer les conseils pour le mois courant via le repository
        $adviceList = $adviceRepository->findAdviceByMonthNumber($currentMonthNumber);

        // Préparer la liste des conseils
        $adviceContent = array_map(fn($advice) => $advice->getContent(), $adviceList);

        return $this->json([
            'month' => $currentMonthNumber,
            'advice' => $adviceContent,
        ]);
    }


    #[Route('/meteo/{ville}', name: 'app_meteo_city', methods: ['GET'])]
    public function getMeteo(string $ville): JsonResponse
    {
        $apiKey = '4e1d9baeff944087956162719242309';

        // Utiliser le cache pour éviter les requêtes répétées
        $meteo = $this->cache->get('meteo_'.$ville, function () use ($ville, $apiKey) {
            $url = 'http://api.weatherapi.com/v1/current.json?key=' . $apiKey . '&q=' . $ville . '&aqi=no';
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return null; // Gérer les erreurs ici
            }

            return $response->toArray(); // Convertir la réponse JSON en tableau PHP
        });

        if ($meteo === null) {
            return new JsonResponse(['error' => 'Unable to retrieve weather data'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($meteo);
    }

    #[Route('/meteo', name: 'app_meteo', methods: ['GET'])]
    public function getMeteoByCity(): JsonResponse
    {
        $apiKey = '4e1d9baeff944087956162719242309';
        $ville = $this->getUser()->getCity();
        
        // Utiliser le cache pour éviter les requêtes répétées
        $meteo = $this->cache->get('meteo_'.$ville, function () use ($ville, $apiKey) {
            $url = 'http://api.weatherapi.com/v1/current.json?key=' . $apiKey . '&q=' . $ville . '&aqi=no';
            $response = $this->httpClient->request('GET', $url);

            if ($response->getStatusCode() !== 200) {
                return null; // Gérer les erreurs ici
            }

            return $response->toArray(); // Convertir la réponse JSON en tableau PHP
        });

        if ($meteo === null) {
            return new JsonResponse(['error' => 'Unable to retrieve weather data'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($meteo);
    }

    #[Route('/conseil', name: 'create_advice', methods: ['POST'])]
    #[IsGranted("ROLE_ADMIN")]
    public function createAdvice(Request $request,EntityManagerInterface $entityManager,MonthRepository $monthRepository, SerializerInterface $serializer): JsonResponse {
        // Désérialiser le contenu de la requête
        $data = json_decode($request->getContent(), true);

        // Créer une nouvelle instance de Advice
        $advice = new Advice();
        $advice->setContent($data['content']);

        // Récupérer la liste des mois depuis la requête
        if (isset($data['months']) && is_array($data['months'])) {
            foreach ($data['months'] as $monthId) {
                $month = $monthRepository->find($monthId);
                if ($month) {
                    $advice->addMonth($month);
                }
            }
        }

        // Persister l'advice
        $entityManager->persist($advice);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Conseil ajouté avec succès'], Response::HTTP_CREATED);
    }

    #[Route('/conseil/{id}', name: 'update_advice', methods: ['PUT'])]
    #[IsGranted("ROLE_ADMIN")]
    public function updateAdvice(Request $request,EntityManagerInterface $entityManager,AdviceRepository $adviceRepository, int $id ,MonthRepository $monthRepository ): JsonResponse {
    // Récupérer le conseil existant
    $advice = $adviceRepository->find($id);

    if (!$advice) {
        return new JsonResponse(['error' => 'Conseil non trouvé'], Response::HTTP_NOT_FOUND);
    }

    // Décoder les données JSON envoyées
    $data = json_decode($request->getContent(), true);

    // Mettre à jour les champs qui sont envoyés
    if (isset($data['content'])) {
        $advice->setContent($data['content']);
    }

    // Mettre à jour les mois associés si nécessaire
    if (isset($data['months'])) {
        // Vider les mois existants et ajouter les nouveaux
        $advice->getMonth()->clear();
        
        foreach ($data['months'] as $monthId) {
            $month = $monthRepository->find($monthId);
            if ($month) {
                $advice->addMonth($month);
            }
        }
    }

    // Enregistrer les modifications
    $entityManager->flush();

    return new JsonResponse(['status' => 'Conseil mis à jour avec succès'], Response::HTTP_OK);
    }

    #[Route('/conseil/{id}', name: 'delete_advice', methods: ['DELETE'])]
    #[IsGranted("ROLE_ADMIN")]
    public function deleteAdvice($id ,AdviceRepository $adviceRepository,EntityManagerInterface $entityManager): JsonResponse {
        // Récupérer le conseil à supprimer
        $advice = $adviceRepository->find($id);

        if (!$advice) {
            return new JsonResponse(['error' => 'Conseil non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Supprimer le conseil
        $entityManager->remove($advice);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Conseil supprimé avec succès'], Response::HTTP_NO_CONTENT);
    }


}
