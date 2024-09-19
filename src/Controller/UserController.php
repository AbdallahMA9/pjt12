<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

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

        if ($form->isValid()) {
            // Hachage du mot de passe
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Sauvegarde de l'utilisateur
            $em->persist($user);
            $em->flush();

            return new JsonResponse(['status' => 'Utilisateur créé avec succès'], 201);
        }

        // Retourner les erreurs de validation
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'error' => 'Données invalides',
            'details' => $data
        ], 400);
    }
}
