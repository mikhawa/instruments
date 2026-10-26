<?php

declare(strict_types=1);

namespace App\Tests\Service\Paiement;

use App\Entity\Commande;
use App\Entity\Paiement;
use App\Entity\Remboursement;
use App\Enum\PrestatairePaiement;
use App\Enum\StatutPaiement;
use App\Service\Paiement\Mollie\MolliePrestataire;
use App\Service\Paiement\PrestataireIndisponibleException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * Adaptateur Mollie sans réseau : format des montants, correspondance des statuts, erreurs.
 */
final class MolliePrestataireTest extends TestCase
{
    private const CLE = 'test_abcdefghijklmnopqrstuvwxyz12';

    public function testCreationEnvoieLeMontantDeLaCommande(): void
    {
        $requetes = [];
        $client = new MockHttpClient(static function (string $methode, string $url, array $options) use (&$requetes): JsonMockResponse {
            $requetes[] = [$methode, $url, $options];

            return new JsonMockResponse(['id' => 'tr_Abc123', 'status' => 'open', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/select-method/Abc123']]], ['http_code' => 201]);
        });

        $cree = (new MolliePrestataire($client, self::CLE))->creer($this->paiement(123405), 'https://boutique.test/retour', 'https://boutique.test/api/webhooks/mollie');

        self::assertSame(['tr_Abc123', 'https://www.mollie.com/checkout/select-method/Abc123'], [$cree->idExterne, $cree->urlPaiement]);
        [$methode, $url, $options] = $requetes[0];
        self::assertSame(['POST', 'https://api.mollie.com/v2/payments'], [$methode, $url]);
        self::assertContains('Authorization: Bearer '.self::CLE, $options['headers']);
        $corps = json_decode($options['body'], true);
        self::assertSame(['currency' => 'EUR', 'value' => '1234.05'], $corps['amount']);
        self::assertSame('Commande CMD-2026-000042', $corps['description']);
        self::assertSame('https://boutique.test/api/webhooks/mollie', $corps['webhookUrl']);
        self::assertSame('fr_BE', $corps['locale']);
    }

    public function testSansAdresseDeNotification(): void
    {
        $client = new MockHttpClient(static function (string $methode, string $url, array $options): JsonMockResponse {
            self::assertArrayNotHasKey('webhookUrl', json_decode($options['body'], true));

            return new JsonMockResponse(['id' => 'tr_Abc123', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/x']]], ['http_code' => 201]);
        });

        (new MolliePrestataire($client, self::CLE))->creer($this->paiement(1000), 'http://localhost/retour', null);
    }

    public function testEtat(): void
    {
        $client = new MockHttpClient([
            new JsonMockResponse(['status' => 'paid', 'amount' => ['value' => '1234.05', 'currency' => 'EUR'], 'method' => 'bancontact', 'mode' => 'test', 'details' => ['cardLabel' => 'Visa', 'cardNumber' => '1234']]),
            new JsonMockResponse(['status' => 'paid', 'amount' => ['value' => '10.00', 'currency' => 'EUR'], 'amountRefunded' => ['value' => '4.00', 'currency' => 'EUR']]),
            new JsonMockResponse(['status' => 'expired', 'amount' => ['value' => '10.00', 'currency' => 'EUR']]),
        ]);
        $mollie = new MolliePrestataire($client, self::CLE);
        $paiement = $this->paiement(123405)->ouvrir('tr_Abc123', null);

        $etat = $mollie->etat($paiement);
        self::assertSame([StatutPaiement::Paye, 123405, 'EUR', 'bancontact'], [$etat->statut, $etat->montant, $etat->devise, $etat->moyen]);
        self::assertSame(['carte' => 'Visa', 'mode' => 'test'], $etat->details, 'Aucune donnée sensible (numéro de carte) n\'est gardée.');

        $etat = $mollie->etat($paiement);
        self::assertSame([StatutPaiement::PartiellementRembourse, 400], [$etat->statut, $etat->montantRembourse]);

        self::assertSame(StatutPaiement::Expire, $mollie->etat($paiement)->statut);
    }

    public function testRemboursement(): void
    {
        $client = new MockHttpClient(static function (string $methode, string $url, array $options): JsonMockResponse {
            self::assertSame('https://api.mollie.com/v2/payments/tr_Abc123/refunds', $url);
            self::assertSame(['currency' => 'EUR', 'value' => '5.00'], json_decode($options['body'], true)['amount']);

            return new JsonMockResponse(['id' => 're_Xyz', 'status' => 'pending'], ['http_code' => 201]);
        });
        $paiement = $this->paiement(1000)->ouvrir('tr_Abc123', null);

        self::assertSame(['re_Xyz', false], (new MolliePrestataire($client, self::CLE))->rembourser(new Remboursement($paiement, 500, 'Test')));
    }

    public function testErreurs(): void
    {
        $paiement = $this->paiement(1000)->ouvrir('tr_Abc123', null);

        try {
            (new MolliePrestataire(new MockHttpClient(new JsonMockResponse(['detail' => 'Missing authentication'], ['http_code' => 401])), self::CLE))->etat($paiement);
            self::fail('401 attendu');
        } catch (PrestataireIndisponibleException $e) {
            self::assertTrue($e->definitif);
            self::assertStringContainsString('Missing authentication', $e->getMessage());
            self::assertStringNotContainsString(self::CLE, $e->getMessage());
        }

        try {
            (new MolliePrestataire(new MockHttpClient(new JsonMockResponse([], ['http_code' => 503])), self::CLE))->etat($paiement);
            self::fail('503 attendu');
        } catch (PrestataireIndisponibleException $e) {
            self::assertFalse($e->definitif, 'Panne passagère : à réessayer.');
        }

        $this->expectException(PrestataireIndisponibleException::class);
        (new MolliePrestataire(new MockHttpClient(), ''))->etat($paiement);
    }

    public function testConversionDesMontants(): void
    {
        self::assertSame(['currency' => 'EUR', 'value' => '0.05'], MolliePrestataire::montant(5, 'EUR'));
        self::assertSame(1999, MolliePrestataire::centimes('19.99'));
        $this->expectException(PrestataireIndisponibleException::class);
        MolliePrestataire::centimes('19.9');
    }

    private function paiement(int $montant): Paiement
    {
        return new Paiement((new Commande())->setNumero('CMD-2026-000042'), PrestatairePaiement::Mollie, $montant);
    }
}
