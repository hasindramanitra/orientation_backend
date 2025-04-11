<?php
namespace App\Controller\API\Auth;

use App\Entity\Profile;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Services\JWTService;
use App\Services\SendMailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{

    #[Route('/signup', name: 'signup', methods:['POST'])]
    public function Register(
        Request $request, 
        ValidatorInterface $validator,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $userPasswordHasherInterface,
        SendMailService $sendMailService,
        JWTService $jWTService,
        UserRepository $userRepository
    ): JsonResponse
    {

        $data = json_decode($request->getContent(), true);

        $email = $data['email'];
        $password = $data['password'];
        $photo = $data['photo'];
        $phone = $data['phone'];
        $adresse = $data['adresse'];
        $currentSchool = $data['currentSchool'];
        $serie = $data['serie'];
        $currentJob = $data['currentJob'];
        $universityCareer = $data['universityCareer'];
        $fullName = $data['fullName'];
        $lastName = $data['lastName'];

        $newUser = new User();

        $hashPassword = $userPasswordHasherInterface->hashPassword(
            $newUser,
            $password
        );

        $isEmailExist = $userRepository->findByEmail($email);

        if($isEmailExist) {
            return new JsonResponse([
                'message' => "Email already used. Please change it."
            ], 400);
        }

        $newUser->setEmail($email)
            ->setPassword($hashPassword);

        $errors = $validator->validate($newUser);

        if (count($errors) > 0) {

            $errorsString = (string)$errors;

            return new JsonResponse($errorsString, 400);
        }

        $em->persist($newUser);

        $newProfile = new Profile();

        $newProfile->setPhoto($photo)
            ->setPhone($phone)
            ->setAdresse($adresse)
            ->setCurrentSchool($currentSchool)
            ->setSerie($serie)
            ->setCurrentJob($currentJob)
            ->setUniversityCareer($universityCareer)
            ->setUser($newUser)
            ->setFullName($fullName)
            ->setLastName($lastName);

        
        
        $em->persist($newProfile);

        $newUser->setProfile($newProfile);

        $em->flush();

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256'
        ];

        $payload = [
            'user_id' => $newUser->getId()
        ];

        $token = $jWTService->generate($header, $payload, $this->getParameter('app.jwtsecret'));



        $sendMailService->send(
            'orientation.mada@gmail.com',
            $newUser->getEmail(),
            'Email confirmation',
            'email-confirmation',
            compact('newUser', 'token')
        );



        return new JsonResponse([
            'message' => 'User created successfully.'
        ], 201);
    }

    /**
     * updates the field isVerifiedUser in User Entity
     * after user registration
     * @param $token
     * @param JWTService $jwt
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManagerInterface
     */
    #[Route('/verify/{token}', name: 'verify_user')]
    public function verifyUser(
        $token,
        JWTService $jwt,
        UserRepository $userRepository,
        EntityManagerInterface $entityManagerInterface
    ): JsonResponse {
        if ($jwt->isValid($token) && !$jwt->isExpired($token) && $jwt->check($token, $this->getParameter('app.jwtsecret'))) {

            $payload = $jwt->getPayload($token);


            $user = $userRepository->find($payload['user_id']);


            if ($user && !$user->isVerified()) {
                $user->setVerified(true);
                $entityManagerInterface->flush($user);
                return new JsonResponse([
                    'status' => JsonResponse::HTTP_OK,
                    'message' => 'Your account is confirmed successfully.'
                ]);
            }
        }

        return new JsonResponse([
            'status' => JsonResponse::HTTP_BAD_REQUEST,
            'message' => 'Token invalid or expired.'
        ]);
    }
}