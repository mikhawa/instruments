<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\CategorieTraduction;
use App\Entity\Instrument;
use App\Entity\InstrumentTraduction;
use App\Entity\MouvementStock;
use App\Entity\Utilisateur;
use App\Enum\EtatInstrument;
use App\Enum\TypeMouvementStock;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Catalogue de développement : 9 catégories (2 niveaux) et 15 instruments
 * traduits en fr / en / es, avec stock initial et mouvement d'entrée.
 *
 * Cas couverts : pièces uniques, instrument ancien restauré, brouillon non publié,
 * rupture de stock, occasion.
 */
final class CatalogueFixtures extends Fixture implements DependentFixtureInterface
{
    /** Préfixe des références d'instruments : self::INSTRUMENT.'CRD-OUD-0001' */
    public const INSTRUMENT = 'instrument-';

    /**
     * clé => [parent, position, [locale => [nom, slug]]]
     */
    private const CATEGORIES = [
        'cordes' => [null, 1, [
            'fr' => ['Cordes', 'cordes'],
            'en' => ['Strings', 'strings'],
            'es' => ['Cuerdas', 'cuerdas'],
        ]],
        'cordes-pincees' => ['cordes', 1, [
            'fr' => ['Cordes pincées', 'cordes-pincees'],
            'en' => ['Plucked strings', 'plucked-strings'],
            'es' => ['Cuerdas pulsadas', 'cuerdas-pulsadas'],
        ]],
        'cordes-frottees' => ['cordes', 2, [
            'fr' => ['Cordes frottées', 'cordes-frottees'],
            'en' => ['Bowed strings', 'bowed-strings'],
            'es' => ['Cuerdas frotadas', 'cuerdas-frotadas'],
        ]],
        'vents' => [null, 2, [
            'fr' => ['Vents', 'vents'],
            'en' => ['Winds', 'winds'],
            'es' => ['Vientos', 'vientos'],
        ]],
        'cornemuses' => ['vents', 1, [
            'fr' => ['Cornemuses', 'cornemuses'],
            'en' => ['Bagpipes', 'bagpipes'],
            'es' => ['Gaitas', 'gaitas'],
        ]],
        'flutes-anches' => ['vents', 2, [
            'fr' => ['Flûtes et anches', 'flutes-et-anches'],
            'en' => ['Flutes and reeds', 'flutes-and-reeds'],
            'es' => ['Flautas y lengüetas', 'flautas-y-lenguetas'],
        ]],
        'percussions' => [null, 3, [
            'fr' => ['Percussions', 'percussions'],
            'en' => ['Percussion', 'percussion'],
            'es' => ['Percusión', 'percusion'],
        ]],
        'tambours-cadre' => ['percussions', 1, [
            'fr' => ['Tambours sur cadre', 'tambours-sur-cadre'],
            'en' => ['Frame drums', 'frame-drums'],
            'es' => ['Tambores de marco', 'tambores-de-marco'],
        ]],
        'percussions-melodiques' => ['percussions', 2, [
            'fr' => ['Percussions mélodiques', 'percussions-melodiques'],
            'en' => ['Melodic percussion', 'melodic-percussion'],
            'es' => ['Percusión melódica', 'percusion-melodica'],
        ]],
    ];

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $categories = $this->chargerCategories($manager);
        $admin = $this->getReference(UtilisateurFixtures::ADMIN, Utilisateur::class);

        foreach ($this->instruments() as $donnees) {
            $instrument = (new Instrument())
                ->setCategorie($categories[$donnees['categorie']])
                ->setReference($donnees['reference'])
                ->setPaysOrigine($donnees['pays'])
                ->setRegionOrigine($donnees['region'] ?? null)
                ->setFacteur($donnees['facteur'] ?? null)
                ->setAnneeFabrication($donnees['annee'] ?? null)
                ->setEtat($donnees['etat'])
                ->setPieceUnique($donnees['pieceUnique'] ?? false)
                ->setPrixHt($donnees['prixHt'])
                ->setPoidsGrammes($donnees['poids'] ?? null)
                ->setDimensions($donnees['dimensions'] ?? null)
                ->setPublished($donnees['publie'] ?? true);

            foreach ($donnees['traductions'] as $locale => $t) {
                $instrument->addTraduction(
                    (new InstrumentTraduction())
                        ->setLocale($locale)
                        ->setNom($t['nom'])
                        ->setSlug($t['slug'])
                        ->setDescriptionCourte($t['court'] ?? null)
                        ->setDescription($t['description'] ?? null)
                        ->setMateriaux($t['materiaux'] ?? null)
                        ->setHistoire($t['histoire'] ?? null)
                );
            }

            $stock = $instrument->getStock();
            $stock->setEmplacement($donnees['emplacement'] ?? null);
            if ($donnees['stock'] > 0) {
                $stock->ajouter($donnees['stock']);
                $manager->persist(new MouvementStock($instrument, TypeMouvementStock::Entree, $donnees['stock'], utilisateur: $admin, commentaire: 'Stock initial'));
            }

            $manager->persist($instrument);
            $this->addReference(self::INSTRUMENT.$donnees['reference'], $instrument);
        }

        $manager->flush();
    }

    /**
     * @return array<string, Categorie>
     */
    private function chargerCategories(ObjectManager $manager): array
    {
        $categories = [];
        foreach (self::CATEGORIES as $cle => [$parent, $position, $traductions]) {
            $categorie = (new Categorie())
                ->setParent(null !== $parent ? $categories[$parent] : null)
                ->setPosition($position);

            foreach ($traductions as $locale => [$nom, $slug]) {
                $categorie->addTraduction((new CategorieTraduction())->setLocale($locale)->setNom($nom)->setSlug($slug));
            }

            $manager->persist($categorie);
            $categories[$cle] = $categorie;
        }

        return $categories;
    }

    /**
     * Prix HT en centimes. Traductions : fr complète, en / es résumées.
     *
     * @return list<array<string, mixed>>
     */
    private function instruments(): array
    {
        return [
            [
                'reference' => 'CRD-OUD-0001', 'categorie' => 'cordes-pincees', 'pays' => 'TR', 'region' => 'Anatolie',
                'facteur' => 'Atelier Özdemir, Istanbul', 'etat' => EtatInstrument::Neuf, 'prixHt' => 89000,
                'poids' => 1800, 'dimensions' => '80 × 37 × 19 cm', 'stock' => 3, 'emplacement' => 'Réserve A-1',
                'traductions' => [
                    'fr' => [
                        'nom' => 'Oud turc', 'slug' => 'oud-turc',
                        'court' => 'Luth à manche court sans frettes, 11 cordes, table en épicéa.',
                        'description' => 'Oud turc fabriqué à la main, caisse bombée à 21 côtes alternant noyer et érable, rosace sculptée. Son chaleureux et profond, idéal pour le makam.',
                        'materiaux' => 'Épicéa, noyer, érable, ébène',
                        'histoire' => 'Ancêtre du luth européen, l\'oud accompagne depuis plus de mille ans les musiques savantes et populaires du Moyen-Orient.',
                    ],
                    'en' => ['nom' => 'Turkish oud', 'slug' => 'turkish-oud', 'court' => 'Fretless short-neck lute, 11 strings, spruce top.'],
                    'es' => ['nom' => 'Laúd árabe turco', 'slug' => 'oud-turco', 'court' => 'Laúd de mástil corto sin trastes, 11 cuerdas, tapa de abeto.'],
                ],
            ],
            [
                'reference' => 'CRD-SAZ-0002', 'categorie' => 'cordes-pincees', 'pays' => 'TR', 'region' => 'Anatolie centrale',
                'etat' => EtatInstrument::Occasion, 'prixHt' => 42000, 'poids' => 1200, 'dimensions' => '100 × 20 × 15 cm', 'stock' => 2,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Saz bağlama', 'slug' => 'saz-baglama',
                        'court' => 'Luth à long manche, 7 cordes en 3 chœurs, frettes mobiles.',
                        'description' => 'Bağlama d\'occasion en très bon état, caisse monoxyle en mûrier. Frettes en nylon réglables pour les micro-intervalles.',
                        'materiaux' => 'Mûrier, épicéa, hêtre',
                    ],
                    'en' => ['nom' => 'Bağlama saz', 'slug' => 'baglama-saz', 'court' => 'Long-neck lute, 7 strings, movable frets.'],
                    'es' => ['nom' => 'Saz bağlama', 'slug' => 'saz-baglama', 'court' => 'Laúd de mástil largo, 7 cuerdas, trastes móviles.'],
                ],
            ],
            [
                'reference' => 'CRD-KOR-0003', 'categorie' => 'cordes-pincees', 'pays' => 'SN', 'region' => 'Casamance',
                'facteur' => 'Famille Cissokho', 'etat' => EtatInstrument::Neuf, 'prixHt' => 125000,
                'poids' => 4500, 'dimensions' => '125 × 55 × 40 cm', 'stock' => 1,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Kora 21 cordes', 'slug' => 'kora-21-cordes',
                        'court' => 'Harpe-luth mandingue, calebasse tendue de peau de vache.',
                        'description' => 'Kora à clés mécaniques, accordage facilité et stable. Calebasse sélectionnée, chevalet en bois dur.',
                        'materiaux' => 'Calebasse, peau de vache, palissandre, nylon',
                        'histoire' => 'Instrument des griots d\'Afrique de l\'Ouest, la kora porte la mémoire des lignées depuis l\'empire du Mali.',
                    ],
                    'en' => ['nom' => '21-string kora', 'slug' => '21-string-kora', 'court' => 'Mandinka harp-lute, calabash with cowhide.'],
                    'es' => ['nom' => 'Kora de 21 cuerdas', 'slug' => 'kora-21-cuerdas', 'court' => 'Arpa-laúd mandinga, calabaza con piel de vaca.'],
                ],
            ],
            [
                'reference' => 'FRT-NYC-0004', 'categorie' => 'cordes-frottees', 'pays' => 'SE', 'region' => 'Uppland',
                'facteur' => 'Verkstad Andersson', 'etat' => EtatInstrument::Neuf, 'prixHt' => 240000,
                'poids' => 2200, 'dimensions' => '90 × 25 × 12 cm', 'stock' => 1,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Nyckelharpa chromatique', 'slug' => 'nyckelharpa-chromatique',
                        'court' => 'Vièle à clavier suédoise, 3 cordes mélodiques et 12 sympathiques.',
                        'description' => 'Nyckelharpa moderne à trois rangs de touches, résonance riche grâce aux cordes sympathiques.',
                        'materiaux' => 'Épicéa, érable, bouleau',
                        'histoire' => 'Attestée dès le XIVe siècle, la nyckelharpa est l\'instrument national de la Suède.',
                    ],
                    'en' => ['nom' => 'Chromatic nyckelharpa', 'slug' => 'chromatic-nyckelharpa', 'court' => 'Swedish keyed fiddle with sympathetic strings.'],
                    'es' => ['nom' => 'Nyckelharpa cromática', 'slug' => 'nyckelharpa-cromatica', 'court' => 'Fídula de teclas sueca con cuerdas simpáticas.'],
                ],
            ],
            [
                'reference' => 'FRT-VIE-0005', 'categorie' => 'cordes-frottees', 'pays' => 'FR', 'region' => 'Jenzat (Allier)',
                'facteur' => 'Maison Pajot', 'annee' => 1905, 'etat' => EtatInstrument::Restaure, 'pieceUnique' => true,
                'prixHt' => 380000, 'poids' => 3500, 'dimensions' => '70 × 30 × 25 cm', 'stock' => 1, 'emplacement' => 'Vitrine 1',
                'traductions' => [
                    'fr' => [
                        'nom' => 'Vielle à roue Pajot 1905', 'slug' => 'vielle-a-roue-pajot-1905',
                        'court' => 'Pièce de collection restaurée, caisse luth, tête sculptée.',
                        'description' => 'Vielle à roue de la célèbre maison Pajot, entièrement restaurée : roue rectifiée, clavier révisé, vernis d\'origine conservé.',
                        'materiaux' => 'Érable, épicéa, os, ivoire végétal',
                        'histoire' => 'Jenzat fut au XIXe siècle la capitale de la lutherie de vielles à roue ; la dynastie Pajot y travailla pendant cinq générations.',
                    ],
                    'en' => ['nom' => 'Pajot hurdy-gurdy (1905)', 'slug' => 'pajot-hurdy-gurdy-1905', 'court' => 'Restored collector\'s piece, lute-backed body.'],
                    'es' => ['nom' => 'Zanfona Pajot (1905)', 'slug' => 'zanfona-pajot-1905', 'court' => 'Pieza de colección restaurada.'],
                ],
            ],
            [
                'reference' => 'FRT-HAR-0006', 'categorie' => 'cordes-frottees', 'pays' => 'NO', 'region' => 'Hardanger',
                'annee' => 1888, 'etat' => EtatInstrument::Ancien, 'pieceUnique' => true, 'prixHt' => 450000,
                'poids' => 600, 'dimensions' => '60 × 21 × 12 cm', 'stock' => 1, 'emplacement' => 'Vitrine 2',
                'traductions' => [
                    'fr' => [
                        'nom' => 'Hardingfele de 1888', 'slug' => 'hardingfele-1888',
                        'court' => 'Violon norvégien ancien à cordes sympathiques, décor à l\'encre.',
                        'description' => 'Hardingfele d\'époque, incrustations de nacre et rosemaling d\'origine. Vendu en l\'état, jouable.',
                        'materiaux' => 'Épicéa, érable, nacre',
                    ],
                    'en' => ['nom' => 'Hardanger fiddle (1888)', 'slug' => 'hardanger-fiddle-1888', 'court' => 'Antique Norwegian fiddle with sympathetic strings.'],
                    'es' => ['nom' => 'Violín de Hardanger (1888)', 'slug' => 'violin-hardanger-1888', 'court' => 'Violín noruego antiguo con cuerdas simpáticas.'],
                ],
            ],
            [
                // Brouillon : non visible sur le site public
                'reference' => 'FRT-ERH-0007', 'categorie' => 'cordes-frottees', 'pays' => 'CN', 'region' => 'Suzhou',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 32000, 'poids' => 900, 'stock' => 4, 'publie' => false,
                'traductions' => [
                    'fr' => ['nom' => 'Erhu', 'slug' => 'erhu', 'court' => 'Vièle chinoise à deux cordes, caisse en python synthétique.'],
                    'en' => ['nom' => 'Erhu', 'slug' => 'erhu', 'court' => 'Two-string Chinese fiddle.'],
                    'es' => ['nom' => 'Erhu', 'slug' => 'erhu', 'court' => 'Violín chino de dos cuerdas.'],
                ],
            ],
            [
                'reference' => 'VNT-UIL-0008', 'categorie' => 'cornemuses', 'pays' => 'IE', 'region' => 'Comté de Clare',
                'facteur' => 'O\'Brien Pipes', 'etat' => EtatInstrument::Neuf, 'prixHt' => 320000, 'poids' => 2000, 'stock' => 1,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Uilleann pipes (demi-jeu)', 'slug' => 'uilleann-pipes-demi-jeu',
                        'court' => 'Cornemuse irlandaise à soufflet, chanter en ré et trois bourdons.',
                        'description' => 'Demi-jeu en ébène et laiton, anches en roseau réglées. Idéal pour débuter en musique irlandaise.',
                        'materiaux' => 'Ébène, laiton, cuir',
                    ],
                    'en' => ['nom' => 'Uilleann pipes (half set)', 'slug' => 'uilleann-pipes-half-set', 'court' => 'Bellows-blown Irish bagpipes in D.'],
                    'es' => ['nom' => 'Gaita uilleann (medio juego)', 'slug' => 'gaita-uilleann-medio-juego', 'court' => 'Gaita irlandesa de fuelle en re.'],
                ],
            ],
            [
                'reference' => 'VNT-BIN-0009', 'categorie' => 'cornemuses', 'pays' => 'FR', 'region' => 'Bretagne',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 98000, 'poids' => 900, 'stock' => 2,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Biniou kozh', 'slug' => 'biniou-kozh',
                        'court' => 'Petite cornemuse bretonne aiguë, jouée en couple avec la bombarde.',
                        'materiaux' => 'Buis, corne, cuir',
                        'histoire' => 'Le couple biniou-bombarde anime les festoù-noz et les noces bretonnes depuis le XVIIIe siècle.',
                    ],
                    'en' => ['nom' => 'Biniou kozh', 'slug' => 'biniou-kozh', 'court' => 'Small high-pitched Breton bagpipe.'],
                    'es' => ['nom' => 'Biniou kozh', 'slug' => 'biniou-kozh', 'court' => 'Pequeña gaita bretona aguda.'],
                ],
            ],
            [
                'reference' => 'VNT-GAI-0010', 'categorie' => 'cornemuses', 'pays' => 'ES', 'region' => 'Galice',
                'facteur' => 'Obradoiro Seivane', 'etat' => EtatInstrument::Neuf, 'prixHt' => 110000, 'poids' => 1500, 'stock' => 2,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Gaita gallega', 'slug' => 'gaita-gallega',
                        'court' => 'Cornemuse galicienne en do, sac en cuir, bourdon unique.',
                        'materiaux' => 'Grenadille, cuir, velours',
                    ],
                    'en' => ['nom' => 'Galician gaita', 'slug' => 'galician-gaita', 'court' => 'Galician bagpipe in C.'],
                    'es' => ['nom' => 'Gaita gallega', 'slug' => 'gaita-gallega', 'court' => 'Gaita gallega en do, fol de cuero.'],
                ],
            ],
            [
                'reference' => 'VNT-DUD-0011', 'categorie' => 'flutes-anches', 'pays' => 'AM', 'region' => 'Erevan',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 19000, 'poids' => 250, 'dimensions' => '35 cm', 'stock' => 6,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Duduk en abricotier', 'slug' => 'duduk-abricotier',
                        'court' => 'Hautbois arménien à anche double, tonalité la.',
                        'materiaux' => 'Bois d\'abricotier, roseau',
                        'histoire' => 'Le duduk et sa musique sont inscrits au patrimoine culturel immatériel de l\'UNESCO depuis 2008.',
                    ],
                    'en' => ['nom' => 'Apricot wood duduk', 'slug' => 'apricot-wood-duduk', 'court' => 'Armenian double-reed oboe in A.'],
                    'es' => ['nom' => 'Duduk de albaricoquero', 'slug' => 'duduk-albaricoquero', 'court' => 'Oboe armenio de doble lengüeta en la.'],
                ],
            ],
            [
                'reference' => 'VNT-SHK-0012', 'categorie' => 'flutes-anches', 'pays' => 'JP', 'region' => 'Kyoto',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 65000, 'poids' => 400, 'dimensions' => '54,5 cm (1,8 shaku)', 'stock' => 2,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Shakuhachi 1,8', 'slug' => 'shakuhachi-1-8',
                        'court' => 'Flûte droite japonaise en bambou madake, 5 trous.',
                        'materiaux' => 'Bambou madake, laque urushi',
                    ],
                    'en' => ['nom' => 'Shakuhachi 1.8', 'slug' => 'shakuhachi-1-8', 'court' => 'Japanese end-blown bamboo flute.'],
                    'es' => ['nom' => 'Shakuhachi 1,8', 'slug' => 'shakuhachi-1-8', 'court' => 'Flauta japonesa de bambú.'],
                ],
            ],
            [
                'reference' => 'PRC-BOD-0013', 'categorie' => 'tambours-cadre', 'pays' => 'IE',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 24000, 'poids' => 1100, 'dimensions' => 'Ø 40 cm', 'stock' => 5,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Bodhrán accordable', 'slug' => 'bodhran-accordable',
                        'court' => 'Tambour irlandais Ø 40 cm, peau de chèvre, tension réglable.',
                        'materiaux' => 'Frêne, peau de chèvre',
                    ],
                    'en' => ['nom' => 'Tunable bodhrán', 'slug' => 'tunable-bodhran', 'court' => 'Irish frame drum, 16", goatskin.'],
                    'es' => ['nom' => 'Bodhrán afinable', 'slug' => 'bodhran-afinable', 'court' => 'Tambor irlandés de 40 cm.'],
                ],
            ],
            [
                'reference' => 'PRC-BEN-0014', 'categorie' => 'tambours-cadre', 'pays' => 'MA',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 8500, 'poids' => 700, 'dimensions' => 'Ø 38 cm', 'stock' => 8,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Bendir', 'slug' => 'bendir',
                        'court' => 'Tambour sur cadre maghrébin à timbre, peau de chèvre.',
                        'materiaux' => 'Bois de hêtre, peau de chèvre, boyau',
                    ],
                    'en' => ['nom' => 'Bendir', 'slug' => 'bendir', 'court' => 'North African frame drum with snares.'],
                    'es' => ['nom' => 'Bendir', 'slug' => 'bendir', 'court' => 'Tambor de marco magrebí con bordón.'],
                ],
            ],
            [
                // Rupture de stock : publié mais indisponible
                'reference' => 'PRC-BAL-0015', 'categorie' => 'percussions-melodiques', 'pays' => 'ML', 'region' => 'Sikasso',
                'etat' => EtatInstrument::Neuf, 'prixHt' => 56000, 'poids' => 8000, 'dimensions' => '120 × 50 × 40 cm', 'stock' => 0,
                'traductions' => [
                    'fr' => [
                        'nom' => 'Balafon pentatonique 17 lames', 'slug' => 'balafon-17-lames',
                        'court' => 'Xylophone mandingue à résonateurs en calebasse.',
                        'materiaux' => 'Palissandre, calebasses, bambou',
                        'histoire' => 'Le balafon « Sosso Bala », conservé en Guinée, est considéré comme vieux de huit siècles.',
                    ],
                    'en' => ['nom' => '17-key pentatonic balafon', 'slug' => '17-key-balafon', 'court' => 'Mandinka xylophone with gourd resonators.'],
                    'es' => ['nom' => 'Balafón pentatónico de 17 teclas', 'slug' => 'balafon-17-teclas', 'court' => 'Xilófono mandinga con calabazas.'],
                ],
            ],
        ];
    }
}
