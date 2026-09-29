<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\HorodatageTrait;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Compte client ou administrateur.
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    use HorodatageTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(options: ['unsigned' => true])]
    #[Groups(['utilisateur:me'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    #[Groups(['utilisateur:me'])]
    private ?string $email = null;

    /** Hash du mot de passe */
    #[ORM\Column]
    private ?string $password = null;

    /** @var list<string> */
    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['utilisateur:me'])]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    #[Groups(['utilisateur:me'])]
    private ?string $nom = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Assert\Length(max: 30)]
    private ?string $telephone = null;

    /** Langue des e-mails envoyés au client */
    #[ORM\Column(length: 5, options: ['default' => 'fr'])]
    #[Assert\Locale]
    #[Groups(['utilisateur:me'])]
    private string $locale = 'fr';

    #[ORM\Column(name: 'is_verified', options: ['default' => false])]
    private bool $verified = false;

    /** @var Collection<int, Adresse> */
    #[ORM\OneToMany(targetEntity: Adresse::class, mappedBy: 'utilisateur', cascade: ['persist'], orphanRemoval: true)]
    private Collection $adresses;

    /** @var Collection<int, Commande> */
    #[ORM\OneToMany(targetEntity: Commande::class, mappedBy: 'utilisateur')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $commandes;

    public function __construct()
    {
        $this->adresses = new ArrayCollection();
        $this->commandes = new ArrayCollection();
    }

    public function __toString(): string
    {
        return sprintf('%s %s <%s>', $this->prenom, $this->nom, $this->email);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @return list<string>
     */
    #[Groups(['utilisateur:me'])]
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Évite de stocker le hash en session (Symfony 7.3+).
     */
    public function __serialize(): array
    {
        $donnees = (array) $this;
        $donnees["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $donnees;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): static
    {
        $this->locale = $locale;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    /**
     * @return Collection<int, Adresse>
     */
    public function getAdresses(): Collection
    {
        return $this->adresses;
    }

    public function addAdresse(Adresse $adresse): static
    {
        if (!$this->adresses->contains($adresse)) {
            $this->adresses->add($adresse);
            $adresse->setUtilisateur($this);
        }

        return $this;
    }

    public function removeAdresse(Adresse $adresse): static
    {
        $this->adresses->removeElement($adresse);

        return $this;
    }

    /**
     * @return Collection<int, Commande>
     */
    public function getCommandes(): Collection
    {
        return $this->commandes;
    }
}
