<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        RateLimiterFactoryInterface $registerAttemptsLimiter,
    ): Response {
        $ip = (string) $request->getClientIp();
        $registerLimiter = $registerAttemptsLimiter->create($ip);

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if (!$registerLimiter->consume(0)->isAccepted()) {
            $this->addFlash('error', 'Trop de tentatives d\'inscription depuis cette adresse. Réessayez dans une minute.');

            return $this->render('registration/register.html.twig', [
                'registrationForm' => $form,
            ], new Response('', Response::HTTP_TOO_MANY_REQUESTS));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $registerLimiter->consume(1);
            $plainPassword = $form->get('plainPassword')->getData();
            if (!\is_string($plainPassword) || '' === $plainPassword) {
                throw new \LogicException('Le mot de passe soumis est invalide.');
            }

            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
