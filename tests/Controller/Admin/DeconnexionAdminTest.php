<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Déconnexion du back-office : lien vers /admin/logout quel que soit le pare-feu de connexion
 * (session partagée avec React), puis retour à l'accueil du site (FRONT_URL, voir .env.test).
 */
final class DeconnexionAdminTest extends WebTestCase
{
    private const EMAIL = 'admin.deconnexion@instruments.test';
    private const MOT_DE_PASSE = 'motdepasse-test';
    private const URL_SITE = 'http://localhost:5173';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();

        $admin = (new Utilisateur())
            ->setEmail(self::EMAIL)
            ->setPrenom('Admin')
            ->setNom('Test')
            ->setLocale('fr')
            ->setRoles(['ROLE_ADMIN'])
            ->setVerified(true);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, self::MOT_DE_PASSE));

        $entityManager = $container->get(EntityManagerInterface::class);
        $entityManager->persist($admin);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->createQuery('DELETE FROM '.Utilisateur::class.' u WHERE u.email = :email')
            ->setParameter('email', self::EMAIL)
            ->execute();

        parent::tearDown();
    }

    public function testDeconnexionApresConnexionAuBackOffice(): void
    {
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Se connecter', [
            'email' => self::EMAIL,
            'password' => self::MOT_DE_PASSE,
        ]);
        self::assertResponseRedirects('/admin');

        $this->verifierDeconnexion();
    }

    public function testDeconnexionApresConnexionDepuisLeSite(): void
    {
        // Connexion côté React : le jeton appartient au pare-feu « main »
        $this->client->jsonRequest('POST', '/api/login', [
            'email' => self::EMAIL,
            'password' => self::MOT_DE_PASSE,
        ]);
        self::assertResponseIsSuccessful();

        $this->verifierDeconnexion();
    }

    private function verifierDeconnexion(): void
    {
        $crawler = $this->client->request('GET', '/admin');
        self::assertResponseIsSuccessful();

        // Menu principal et menu utilisateur : jamais /api/logout (POST uniquement)
        self::assertSelectorNotExists('a[href*="/api/logout"]');
        $liens = $crawler->filter('a:contains("Déconnexion")');
        self::assertGreaterThan(0, $liens->count());
        foreach ($liens as $lien) {
            self::assertSame('/admin/logout', $lien->getAttribute('href'));
        }

        $this->client->request('GET', '/admin/logout');
        self::assertResponseRedirects(self::URL_SITE);

        // Session invalidée : déconnecté du back-office et du site
        $this->client->request('GET', '/admin');
        self::assertResponseRedirects('/admin/login');
        $this->client->request('GET', '/api/me');
        self::assertResponseStatusCodeSame(401);
    }
}
