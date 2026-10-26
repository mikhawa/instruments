<?php

declare(strict_types=1);

namespace App\Tests\Service\Paiement;

use App\Service\Paiement\CreationPaiement;
use App\Service\Paiement\Virement\CommunicationStructuree;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CommunicationStructureeTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string}>
     */
    public static function fournirReferences(): iterable
    {
        yield 'petite référence' => [303, '+++000/0000/30312+++'];
        // 97 × 1 234 567 : reste 0, le contrôle vaut 97
        yield 'multiple de 97' => [119752999, '+++011/9752/99997+++'];
        yield 'maximum' => [9_999_999_999, '+++999/9999/99948+++'];
    }

    #[DataProvider('fournirReferences')]
    public function testDepuis(int $reference, string $attendue): void
    {
        self::assertSame($attendue, CommunicationStructuree::depuis($reference));
        self::assertTrue(CommunicationStructuree::estValide($attendue));
    }

    public function testControleFauxRefuse(): void
    {
        self::assertFalse(CommunicationStructuree::estValide('+++000/0000/30313+++'));
        self::assertFalse(CommunicationStructuree::estValide('000/0000/30312'));
        self::assertFalse(CommunicationStructuree::estValide('+++000/0000/00097+++'), 'Référence nulle.');
    }

    public function testReferenceHorsLimites(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CommunicationStructuree::depuis(0);
    }

    public function testJoursOuvrables(): void
    {
        $bruxelles = new \DateTimeZone('Europe/Brussels');
        // Jeudi + 5 jours ouvrables = jeudi suivant, 23 h 59 (week-end sauté)
        self::assertSame('2026-11-05 23:59', CreationPaiement::ajouterJoursOuvrables(new \DateTimeImmutable('2026-10-29 10:00', $bruxelles), 5)->format('Y-m-d H:i'));
        // Samedi + 1 jour ouvrable = lundi
        self::assertSame('2026-11-02 23:59', CreationPaiement::ajouterJoursOuvrables(new \DateTimeImmutable('2026-10-31 10:00', $bruxelles), 1)->format('Y-m-d H:i'));
    }
}
