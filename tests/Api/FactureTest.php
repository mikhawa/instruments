<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Categorie;
use App\Entity\CategorieTraduction;
use App\Entity\Commande;
use App\Entity\Facture;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\Utilisateur;
use App\Enum\ClasseLivraison;
use App\Enum\EtatInstrument;
use App\Enum\RegimeTva;
use App\Enum\StatutCommande;
use App\Enum\StatutPaiement;
use App\Enum\TypeFacture;
use App\Service\Facturation\NumerotationFacture;
use App\Service\Paiement\RemboursementCommande;
use App\Service\Paiement\SynchronisationPaiement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Factures et notes de crédit (phase 4, hypothèses de EmissionFacture) : émission au paiement,
 * régime de la marge, numérotation sans trou, PDF, remboursements depuis le back-office.
 * Paiements par le faux prestataire « simulation » ; retrait à l'atelier (pas de frais de port).
 */
final class FactureTest extends WebTestCase
{
    private const PREFIXE = 'TST-FAC-';
    private const EMAIL_INVITE = 'invite.facture@instruments.test';
    private const EMAIL_ADMIN = 'admin.facture@instruments.test';
    private const MOT_DE_PASSE = 'motdepasse-test';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $container = static::getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        $categorie = (new Categorie())->addTraduction((new CategorieTraduction())->setLocale('fr')->setNom('Test facture')->setSlug('test-facture'));
        $entityManager->persist($categorie);
        foreach ([
            // clé => [prix HT, stock, régime, prix d'achat]
            'flute' => [20000, 5, RegimeTva::Normal, null],          // 242,00 € TTC
            'luth' => [100000, 2, RegimeTva::MargeOccasion, 50000],  // 1 210,00 € TTC, acheté 500 €
        ] as $cle => [$prixHt, $stock, $regime, $prixAchat]) {
            $instrument = (new Instrument())
                ->setCategorie($categorie)
                ->setReference(self::PREFIXE.strtoupper($cle))
                ->setEtat(EtatInstrument::Occasion)
                ->setPrixHt($prixHt)
                ->setRegimeTva($regime)
                ->setPrixAchat($prixAchat)
                ->setClasseLivraison(ClasseLivraison::Colis)
                ->setPublished(true)
                ->addTraduction((new InstrumentTraduction())->setLocale('fr')->setNom('Test '.$cle)->setSlug('test-facture-'.$cle));
            $instrument->getStock()->ajouter($stock);
            $entityManager->persist($instrument);
        }

        $admin = (new Utilisateur())->setEmail(self::EMAIL_ADMIN)->setPrenom('Admin')->setNom('Facture')->setLocale('fr')->setRoles(['ROLE_ADMIN'])->setVerified(true);
        $admin->setPassword($container->get(UserPasswordHasherInterface::class)->hashPassword($admin, self::MOT_DE_PASSE));
        $entityManager->persist($admin);
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        $connexion = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $instruments = 'SELECT id FROM instrument WHERE reference LIKE ?';
        $commandes = "SELECT DISTINCT l.commande_id FROM ligne_commande l WHERE l.instrument_id IN ($instruments)";
        $connexion->executeStatement("DELETE FROM facture WHERE facture_origine_id IS NOT NULL AND commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM facture WHERE commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE r FROM remboursement r JOIN paiement p ON p.id = r.paiement_id WHERE p.commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM paiement WHERE commande_id IN ($commandes)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE FROM mouvement_stock WHERE instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE c FROM commande c JOIN ligne_commande l ON l.commande_id = c.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement("DELETE p FROM panier p JOIN ligne_panier l ON l.panier_id = p.id WHERE l.instrument_id IN ($instruments)", [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE FROM instrument WHERE reference LIKE ?', [self::PREFIXE.'%']);
        $connexion->executeStatement('DELETE c FROM categorie c JOIN categorie_traduction t ON t.categorie_id = c.id WHERE t.slug = ?', ['test-facture']);
        $connexion->executeStatement('DELETE FROM utilisateur WHERE email = ?', [self::EMAIL_ADMIN]);

        parent::tearDown();
    }

    public function testFactureEmiseAuPaiement(): void
    {
        ['numero' => $numero, 'jetonSuivi' => $jeton] = $this->commanderEtPayer(['flute']);

        $vue = $this->requete('GET', "/api/commandes/$numero?jeton=$jeton");
        self::assertCount(1, $vue['factures']);
        self::assertSame(['facture', 24200], [$vue['factures'][0]['type'], $vue['factures'][0]['totalTtc']]);
        $numeroFacture = $vue['factures'][0]['numero'];
        self::assertMatchesRegularExpression('/^F\d{4}-\d{6}$/', $numeroFacture);

        $facture = $this->facture($numeroFacture);
        self::assertSame([20000, 4200, 24200], [$facture->getTotalHt(), $facture->getTotalTva(), $facture->getTotalTtc()]);
        self::assertSame([['taux' => 2100, 'base' => 20000, 'tva' => 4200]], $facture->getVentilationTva());
        self::assertSame([], $facture->getMentions());
        self::assertSame('BE0123456749', $facture->getVendeur()['tva']);
        self::assertSame(self::EMAIL_INVITE, $facture->getClient()['email']);
        self::assertNotNull($facture->getPayeeAt());
        self::assertSame('simulation', $facture->getMoyenPaiement());

        // Rejeu de la synchronisation : pas de seconde facture
        static::getContainer()->get(SynchronisationPaiement::class)->synchroniser($this->commande($numero)->getDernierPaiement());
        self::assertCount(1, $this->commande($numero)->getFactures());

        // PDF : avec le jeton du lien de suivi seulement
        $this->client->request('GET', "/api/commandes/$numero/factures/$numeroFacture.pdf?jeton=$jeton");
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
        self::assertStringStartsWith('%PDF-', (string) $this->client->getInternalResponse()->getContent());
        $this->client->getCookieJar()->clear();
        $this->client->request('GET', "/api/commandes/$numero/factures/$numeroFacture.pdf");
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', "/api/commandes/$numero/factures/F2000-000001.pdf?jeton=$jeton");
        self::assertResponseStatusCodeSame(404);
    }

    public function testRegimeDeLaMarge(): void
    {
        $this->requete('POST', '/api/panier/lignes', ['instrument' => $this->iri('luth'), 'quantite' => 1]);
        $panier = $this->requete('GET', '/api/panier');
        self::assertSame([121000, 121000, 0], [$panier['totalTtc'], $panier['totalHt'], $panier['totalTva']], 'Aucune TVA visible sous le régime de la marge.');

        ['numero' => $numero] = $this->commanderEtPayer(['flute']);
        $commande = $this->commande($numero);

        $ligneLuth = $commande->getLignes()->filter(static fn ($ligne): bool => str_ends_with($ligne->getReference(), 'LUTH'))->first();
        self::assertSame(RegimeTva::MargeOccasion, $ligneLuth->getRegimeTva());
        self::assertSame(121000, $ligneLuth->getTotalHt());
        // TVA comprise dans la marge : (1 210 − 500) × 21 / 121
        self::assertSame(12322, $ligneLuth->getTvaMarge());
        self::assertSame(20000 + 121000, $commande->getTotalHt());
        self::assertSame(4200, $commande->getTotalTva());

        $facture = $commande->getFactures()->first();
        self::assertSame([['taux' => 2100, 'base' => 20000, 'tva' => 4200]], $facture->getVentilationTva(), 'Seule la flûte (régime normal) ventile de la TVA.');
        self::assertSame(['mention.marge_occasion'], $facture->getMentions());
        $lignesMarge = array_values(array_filter($facture->getLignes(), static fn (array $l): bool => 'marge_occasion' === $l['regimeTva']));
        self::assertSame([null, 0, 121000], [$lignesMarge[0]['tauxTva'], $lignesMarge[0]['totalTva'], $lignesMarge[0]['totalTtc']]);
        self::assertSame(145200, $facture->getTotalTtc());
    }

    public function testNumerotationSansTrou(): void
    {
        $numerotation = static::getContainer()->get(NumerotationFacture::class);
        $connexion = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        $date = new \DateTimeImmutable('2099-06-01');

        // Une émission annulée rend son numéro
        $connexion->beginTransaction();
        $premier = $numerotation->suivant(TypeFacture::NoteCredit, $date);
        $connexion->rollBack();
        self::assertSame($premier, $numerotation->suivant(TypeFacture::NoteCredit, $date));
        self::assertSame(\sprintf('NC2099-%06d', (int) substr($premier, -6) + 1), $numerotation->suivant(TypeFacture::NoteCredit, $date));

        $connexion->executeStatement('DELETE FROM sequence_facture WHERE annee = 2099');
    }

    public function testRemboursementPartielPuisTotalAvecAnnulation(): void
    {
        ['numero' => $numero] = $this->commanderEtPayer(['flute']);
        $service = static::getContainer()->get(RemboursementCommande::class);
        $commande = $this->commande($numero);
        self::assertSame(24200, $service->remboursable($commande));

        // Geste commercial : note de crédit au prorata de la facture
        $service->rembourser($commande, 5000, 'Geste commercial', null);
        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Payee, $commande->getStatut());
        self::assertSame(StatutPaiement::PartiellementRembourse, $commande->getDernierPaiement()->getStatut());
        $note = $commande->getFactures()->last();
        self::assertSame(TypeFacture::NoteCredit, $note->getType());
        self::assertMatchesRegularExpression('/^NC\d{4}-\d{6}$/', $note->getNumero());
        self::assertSame([4132, 868, 5000], [$note->getTotalHt(), $note->getTotalTva(), $note->getTotalTtc()]);
        self::assertSame($commande->getFactures()->first(), $note->getFactureOrigine());
        self::assertSame('Geste commercial', $note->getMotif());
        self::assertSame(19200, $service->remboursable($commande));

        // Annulation avant expédition : stock libéré, solde remboursé, commande « remboursée »
        self::assertTrue($service->peutAnnuler($commande));
        $service->rembourser($commande, 19200, 'Annulation à la demande du client', null, annuler: true);
        $commande = $this->commande($numero);
        self::assertSame(StatutCommande::Remboursee, $commande->getStatut());
        self::assertSame(StatutPaiement::Rembourse, $commande->getDernierPaiement()->getStatut());
        self::assertSame(0, $this->instrument('flute')->getStock()->getQuantiteReservee());
        $notes = array_filter($commande->getFactures()->toArray(), static fn (Facture $f): bool => $f->isNoteCredit());
        self::assertSame(24200, array_sum(array_map(static fn (Facture $f): int => $f->getTotalTtc(), $notes)));

        $this->expectException(\DomainException::class);
        $service->rembourser($this->commande($numero), 1, 'Trop', null);
    }

    public function testFormulaireDeRemboursementDuBackOffice(): void
    {
        ['numero' => $numero] = $this->commanderEtPayer(['flute']);
        $id = $this->commande($numero)->getId();

        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Se connecter', ['email' => self::EMAIL_ADMIN, 'password' => self::MOT_DE_PASSE]);
        $crawler = $this->client->request('GET', '/admin/commande/'.$id);
        self::assertResponseIsSuccessful();
        $texte = $crawler->filter('body')->text();
        self::assertStringContainsString('Rembourser', $texte);
        self::assertStringNotContainsString('Annuler la commande', $texte);

        $crawler = $this->client->request('GET', '/admin/commande/'.$id.'/remboursement');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Rembourser')->form(), ['form[montant]' => '10,00', 'form[motif]' => 'Rayure sur la caisse']);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'remboursement de 10,00 €');

        $commande = $this->commande($numero);
        self::assertSame(1000, $commande->getDernierPaiement()->getMontantRembourse());
        self::assertSame(StatutCommande::Payee, $commande->getStatut());

        $this->client->request('GET', '/admin/facture');
        self::assertResponseIsSuccessful();
        $note = $commande->getFactures()->last();
        $this->client->request('GET', '/admin/facture/'.$note->getId().'/pdf');
        self::assertResponseHeaderSame('Content-Type', 'application/pdf');
    }

    public function testRegimeDeLaMargeExigeLePrixDAchat(): void
    {
        $instrument = $this->instrument('luth')->setPrixAchat(null);
        $violations = static::getContainer()->get(ValidatorInterface::class)->validate($instrument);
        self::assertContains('prixAchat', array_map(static fn ($v): string => $v->getPropertyPath(), iterator_to_array($violations)));
    }

    /**
     * Ajoute les instruments au panier, commande en retrait à l'atelier et paie en ligne (simulation).
     *
     * @param list<string> $cles
     *
     * @return array<string, mixed>
     */
    private function commanderEtPayer(array $cles): array
    {
        foreach ($cles as $cle) {
            $this->requete('POST', '/api/panier/lignes', ['instrument' => $this->iri($cle), 'quantite' => 1]);
            self::assertResponseIsSuccessful();
        }
        $total = $this->requete('GET', '/api/panier')['totalTtc'];
        $commande = $this->requete('POST', '/api/commandes', [
            'email' => self::EMAIL_INVITE,
            'adresseFacturation' => ['prenom' => 'Ana', 'nom' => 'Test', 'rue' => '1 rue des Luthiers', 'codePostal' => '1000', 'ville' => 'Bruxelles', 'pays' => 'BE'],
            'modeLivraison' => 'retrait_atelier',
            'accepteCgv' => true,
            'totalTtcAttendu' => $total,
        ]);
        self::assertResponseStatusCodeSame(201);

        $url = $this->requete('POST', "/api/commandes/{$commande['numero']}/paiements?jeton={$commande['jetonSuivi']}", ['moyen' => 'en_ligne'])['urlPaiement'];
        $this->requete('POST', '/api/paiements/simulation/'.basename($url), ['issue' => 'paye']);
        self::assertResponseIsSuccessful();

        return $commande;
    }

    private function iri(string $cle): string
    {
        return '/api/instruments/'.$this->instrument($cle)->getId();
    }

    /**
     * @param array<string, mixed>|null $corps
     *
     * @return array<mixed>
     */
    private function requete(string $methode, string $url, ?array $corps = null): array
    {
        $this->client->request($methode, $url, server: array_filter([
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => null === $corps ? null : 'application/json',
        ]), content: null === $corps ? null : json_encode($corps, \JSON_THROW_ON_ERROR));

        return json_decode((string) $this->client->getResponse()->getContent() ?: '[]', true, flags: \JSON_THROW_ON_ERROR);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function commande(string $numero): Commande
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Commande::class)->findOneBy(['numero' => $numero]);
    }

    private function facture(string $numero): Facture
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Facture::class)->findOneBy(['numero' => $numero]);
    }

    private function instrument(string $cle): Instrument
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(Instrument::class)->findOneBy(['reference' => self::PREFIXE.strtoupper($cle)]);
    }
}
