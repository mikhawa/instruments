<?php

declare(strict_types=1);

namespace App\Tests\Workflow;

use App\Entity\Commande;
use App\Enum\StatutCommande;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

/**
 * Cycle de vie d'une commande : transitions autorisées et interdites (aucune base nécessaire).
 */
final class CommandeWorkflowTest extends KernelTestCase
{
    private WorkflowInterface $workflow;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->workflow = static::getContainer()->get('state_machine.commande');
    }

    public function testMarquageInitial(): void
    {
        $commande = $this->commande();

        self::assertSame(StatutCommande::EnAttentePaiement, $commande->getStatut());
        self::assertSame(['en_attente_paiement' => 1], $this->workflow->getMarking($commande)->getPlaces());
    }

    public function testParcoursComplet(): void
    {
        $commande = $this->commande();

        $etapes = [
            'payer' => StatutCommande::Payee,
            'preparer' => StatutCommande::EnPreparation,
            'expedier' => StatutCommande::Expediee,
            'livrer' => StatutCommande::Livree,
            'retourner' => StatutCommande::Retournee,
            'rembourser' => StatutCommande::Remboursee,
        ];

        foreach ($etapes as $transition => $statutAttendu) {
            self::assertTrue($this->workflow->can($commande, $transition), $transition);
            $this->workflow->apply($commande, $transition);
            self::assertSame($statutAttendu, $commande->getStatut(), $transition);

            if ('payer' === $transition) {
                // Sera fait par l'écouteur de la transition « payer » (phase 3)
                $commande->setPaidAt(new \DateTimeImmutable());
            }
        }

        self::assertSame([], $this->workflow->getEnabledTransitions($commande), 'Remboursée est un état final');
    }

    public function testCommandeExpireeSansPaiementNonRemboursable(): void
    {
        $commande = $this->commande();

        $this->workflow->apply($commande, 'expirer');
        self::assertSame(StatutCommande::Annulee, $commande->getStatut());

        self::assertFalse($this->workflow->can($commande, 'rembourser'));
    }

    public function testAnnulationApresPaiementPuisRemboursement(): void
    {
        $commande = $this->commande();

        $this->workflow->apply($commande, 'payer');
        $commande->setPaidAt(new \DateTimeImmutable());
        $this->workflow->apply($commande, 'annuler');

        $this->workflow->apply($commande, 'rembourser');
        self::assertSame(StatutCommande::Remboursee, $commande->getStatut());
    }

    #[DataProvider('fournirAnnulationsPossibles')]
    public function testAnnulationAvantExpedition(StatutCommande $depart): void
    {
        $commande = $this->commande()->setStatut($depart);

        $this->workflow->apply($commande, 'annuler');

        self::assertSame(StatutCommande::Annulee, $commande->getStatut());
    }

    /**
     * @return iterable<string, array{StatutCommande}>
     */
    public static function fournirAnnulationsPossibles(): iterable
    {
        yield 'en attente de paiement' => [StatutCommande::EnAttentePaiement];
        yield 'payée' => [StatutCommande::Payee];
        yield 'en préparation' => [StatutCommande::EnPreparation];
    }

    #[DataProvider('fournirTransitionsInterdites')]
    public function testTransitionInterdite(StatutCommande $depart, string $transition): void
    {
        $commande = $this->commande()->setStatut($depart);

        self::assertFalse($this->workflow->can($commande, $transition));

        $this->expectException(LogicException::class);
        $this->workflow->apply($commande, $transition);
    }

    /**
     * @return iterable<string, array{StatutCommande, string}>
     */
    public static function fournirTransitionsInterdites(): iterable
    {
        yield 'expédier sans paiement' => [StatutCommande::EnAttentePaiement, 'expedier'];
        yield 'payer une commande annulée' => [StatutCommande::Annulee, 'payer'];
        yield 'annuler une commande expédiée' => [StatutCommande::Expediee, 'annuler'];
        yield 'expirer une commande payée' => [StatutCommande::Payee, 'expirer'];
        yield 'préparer sans paiement' => [StatutCommande::EnAttentePaiement, 'preparer'];
        yield 'retourner sans livraison' => [StatutCommande::Expediee, 'retourner'];
        yield 'rembourser une commande livrée' => [StatutCommande::Livree, 'rembourser'];
        yield 'rembourser deux fois' => [StatutCommande::Remboursee, 'rembourser'];
    }

    /**
     * Commande minimale : les écouteurs du workflow envoient des e-mails, l'adresse est obligatoire.
     */
    private function commande(): Commande
    {
        return (new Commande())->setEmail('client@instruments.test');
    }
}
