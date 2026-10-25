<?php

declare(strict_types=1);

namespace App\ApiResource\Commande;

use App\Entity\Adresse;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Adresse saisie dans le tunnel de commande ; elle est copiée (figée) dans la commande.
 */
final class AdresseSaisie
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $prenom = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $nom = '';

    #[Assert\Length(max: 150)]
    public ?string $societe = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $rue = '';

    #[Assert\Length(max: 255)]
    public ?string $complement = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    #[Assert\Regex('/^[A-Za-z0-9 -]+$/', message: 'Code postal invalide.')]
    public string $codePostal = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $ville = '';

    #[Assert\NotBlank]
    #[Assert\Country]
    public string $pays = '';

    /**
     * Adresse non persistée, au format attendu par Commande::setAdresseLivraison().
     */
    public function versAdresse(): Adresse
    {
        return (new Adresse())
            ->setPrenom(trim($this->prenom))
            ->setNom(trim($this->nom))
            ->setSociete($this->vide($this->societe))
            ->setRue(trim($this->rue))
            ->setComplement($this->vide($this->complement))
            ->setCodePostal(strtoupper(trim($this->codePostal)))
            ->setVille(trim($this->ville))
            ->setPays(strtoupper($this->pays));
    }

    private function vide(?string $valeur): ?string
    {
        $valeur = null !== $valeur ? trim($valeur) : null;

        return '' === $valeur ? null : $valeur;
    }
}
