<?php

declare(strict_types=1);

namespace App\Entity\Trait;

use Doctrine\ORM\Mapping as ORM;

/**
 * Colonnes created_at / updated_at (UTC) mises à jour automatiquement.
 * L'entité utilisatrice doit porter #[ORM\HasLifecycleCallbacks].
 */
trait HorodatageTrait
{
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    public function initialiserHorodatage(): void
    {
        $maintenant = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->createdAt ??= $maintenant;
        $this->updatedAt = $maintenant;
    }

    #[ORM\PreUpdate]
    public function actualiserHorodatage(): void
    {
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
