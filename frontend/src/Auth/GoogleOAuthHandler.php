// api/src/Auth/GoogleOAuthHandler.php
<?php

declare(strict_types=1);

namespace App\Auth;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Google\Client as GoogleClient;
use Google\Service\Oauth2;

class GoogleOAuthHandler
{
    private GoogleClient $googleClient;
    private EntityManagerInterface $entityManager;
    private string $jwtSecret;

    public function __construct(EntityManagerInterface $entityManager, string $jwtSecret)
    {
        $this->entityManager = $entityManager;
        $this->jwtSecret = $jwtSecret;
        
        $this->googleClient = new GoogleClient();
        $this->googleClient->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $this->googleClient->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $this->googleClient->setRedirectUri($_ENV['APP_URL'] . '/auth/google/callback');
        $this->googleClient->addScope('email');
        $this->googleClient->addScope('profile');
    }

    /**
     * Get Google OAuth authorization URL
     */
    public function getAuthUrl(): string
    {
        return $this->googleClient->createAuthUrl();
    }

    /**
     * Handle Google OAuth callback and authenticate user
     */
    public function handleCallback(string $authCode): array
    {
        try {
            // Exchange authorization code for access token
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($authCode);
            
            if (isset($token['error'])) {
                throw new \Exception('Error fetching access token: ' . $token['error_description']);
            }

            $this->googleClient->setAccessToken($token);

            // Get user info from Google
            $oauth2 = new Oauth2($this->googleClient);
            $userInfo = $oauth2->userinfo->get();

            // Find or create user
            $user = $this->findOrCreateUser($userInfo);

            // Generate JWT token
            $jwt = $this->generateJwtToken($user);

            return [
                'success' => true,
                'user' => $user->jsonSerialize(),
                'token' => $jwt,
                'message' => 'Authentication successful'
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Verify JWT token and return user
     */
    public function verifyToken(string $token): ?User
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
            
            $user = $this->entityManager
                ->getRepository(User::class)
                ->find($decoded->sub);

            if ($user && $user->isActive()) {
                return $user;
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find existing user or create new one
     */
    private function findOrCreateUser($userInfo): User
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        
        // Try to find user by Google ID first
        $user = $userRepository->findOneBy(['googleId' => $userInfo->id]);
        
        // If not found, try by email
        if (!$user) {
            $user = $userRepository->findOneBy(['email' => $userInfo->email]);
            
            // If found by email, update with Google ID
            if ($user) {
                $user->setGoogleId($userInfo->id);
            }
        }

        // Create new user if not found
        if (!$user) {
            $user = new User();
            $user->setEmail($userInfo->email);
            $user->setGoogleId($userInfo->id);
            $this->entityManager->persist($user);
        }

        // Update user information
        $user->setFirstName($userInfo->givenName ?? '');
        $user->setLastName($userInfo->familyName ?? '');
        $user->setProfilePicture($userInfo->picture ?? null);
        $user->updateLastLogin();

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Generate JWT token for user
     */
    private function generateJwtToken(User $user): string
    {
        $now = time();
        $expiry = $now + (int)($_ENV['JWT_EXPIRY'] ?? 86400); // Default 24 hours

        $payload = [
            'iss' => $_ENV['APP_URL'], // Issuer
            'sub' => $user->getId(), // Subject (user ID)
            'iat' => $now, // Issued at
            'exp' => $expiry, // Expiration
            'email' => $user->getEmail(),
            'name' => $user->getFullName()
        ];

        return JWT::encode($payload, $this->jwtSecret, 'HS256');
    }

    /**
     * Refresh user's access token
     */
    public function refreshToken(string $refreshToken): ?string
    {
        try {
            $this->googleClient->refreshToken($refreshToken);
            $token = $this->googleClient->getAccessToken();
            
            return $token['access_token'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}