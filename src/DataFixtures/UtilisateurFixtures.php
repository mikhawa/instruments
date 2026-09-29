<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Adresse;
use App\Entity\Utilisateur;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de développement : un administrateur et trois clients (BE, BE, ES).
 * Mots de passe : « admin » pour l'administrateur, « client » pour les clients.
 */
final class UtilisateurFixtures extends Fixture
{
    public const ADMIN = 'utilisateur-admin';
    public const MARIE = 'utilisateur-marie';
    public const JAN = 'utilisateur-jan';
    public const LUCIA = 'utilisateur-lucia';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = $this->creerUtilisateur('admin@instruments.test', 'admin', 'Alex', 'Martin', 'fr', ['ROLE_ADMIN']);
        $manager->persist($admin);
        $this->addReference(self::ADMIN, $admin);

        $clients = [
            self::MARIE => [
                'marie.dubois@example.test', 'Marie', 'Dubois', 'fr', '+32 470 12 34 56',
                ['Maison', 'Rue Saint-Gilles 42', null, '4000', 'Liège', 'BE'],
            ],
            self::JAN => [
                'jan.peeters@example.test', 'Jan', 'Peeters', 'en', '+32 485 98 76 54',
                ['Atelier', 'Veldstraat 118', 'Bus 3', '9000', 'Gent', 'BE'],
            ],
            self::LUCIA => [
                'lucia.garcia@example.test', 'Lucía', 'García', 'es', '+34 612 345 678',
                ['Casa', 'Rúa do Príncipe 27', '2º B', '36202', 'Vigo', 'ES'],
            ],
        ];

        foreach ($clients as $reference => [$email, $prenom, $nom, $locale, $telephone, $adresse]) {
            $client = $this->creerUtilisateur($email, 'client', $prenom, $nom, $locale)
                ->setTelephone($telephone);

            [$libelle, $rue, $complement, $codePostal, $ville, $pays] = $adresse;
            $client->addAdresse(
                (new Adresse())
                    ->setLibelle($libelle)
                    ->setPrenom($prenom)
                    ->setNom($nom)
                    ->setRue($rue)
                    ->setComplement($complement)
                    ->setCodePostal($codePostal)
                    ->setVille($ville)
                    ->setPays($pays)
                    ->setDefault(true)
            );

            $manager->persist($client);
            $this->addReference($reference, $client);
        }

        $manager->flush();
    }

    /**
     * @param list<string> $roles
     */
    private function creerUtilisateur(string $email, string $motDePasse, string $prenom, string $nom, string $locale, array $roles = []): Utilisateur
    {
        $utilisateur = (new Utilisateur())
            ->setEmail($email)
            ->setPrenom($prenom)
            ->setNom($nom)
            ->setLocale($locale)
            ->setRoles($roles)
            ->setVerified(true);

        return $utilisateur->setPassword($this->hasher->hashPassword($utilisateur, $motDePasse));
    }
}
