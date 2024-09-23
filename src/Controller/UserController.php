<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class UserController extends AbstractController
{
    #[Route('/user', name: 'app_user', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $userPasswordHasher): JsonResponse
    {
        $user = new User();

        // Décoder les données JSON envoyées via Postman
        $data = json_decode($request->getContent(), true);

        // Création du formulaire et passage des données
        $form = $this->createForm(UserType::class, $user);
        $form->submit($data);

        //dd($data);

        if ($form->isValid()) {
            // Hachage du mot de passe
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            // Sauvegarde de l'utilisateur
            $em->persist($user);
            $em->flush();

            return new JsonResponse(['status' => 'Utilisateur créé avec succès'], Response::HTTP_CREATED);
        }

        // Retourner les erreurs de validation
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'error' => 'Données invalides',
            'details' => $errors
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/user/{id}', name: 'update_user', methods: ['PUT'])]
    //#[IsGranted("ROLE_ADMIN")]
    public function updateUser($id ,Request $request,UserRepository $userRepository,EntityManagerInterface $entityManager,UserPasswordHasherInterface $userPasswordHasher): JsonResponse {
        // Récupérer l'utilisateur à mettre à jour
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], Response::HTTP_NOT_FOUND);
        }

        // Décoder les données JSON envoyées via Postman
        $data = json_decode($request->getContent(), true);

        // Mettre à jour les informations de l'utilisateur
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }

        if (isset($data['city'])) {
            $user->setCity($data['city']);
        }



        // Sauvegarde des changements
        $entityManager->flush();

        return new JsonResponse(['status' => 'Utilisateur mis à jour avec succès'], Response::HTTP_OK);
    }

    #[Route('/user/{id}', name: 'delete_user', methods: ['DELETE'])]
    //#[IsGranted("ROLE_ADMIN")]
    public function deleteUser(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupérer l'utilisateur à supprimer
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Utilisateur non trouvé'], JsonResponse::HTTP_NOT_FOUND);
        }

        // Supprimer l'utilisateur
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(['status' => 'Utilisateur supprimé avec succès'], JsonResponse::HTTP_NO_CONTENT);
    }

}
