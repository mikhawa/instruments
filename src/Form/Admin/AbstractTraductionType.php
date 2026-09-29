<?php

declare(strict_types=1);

namespace App\Form\Admin;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\String\Slugger\SluggerInterface;

/**
 * Base des sous-formulaires de traduction du back-office :
 * choix de la langue parmi les locales activées et slug généré depuis le nom s'il est laissé vide.
 */
abstract class AbstractTraductionType extends AbstractType
{
    /**
     * @param list<string> $localesActives
     */
    public function __construct(
        private readonly SluggerInterface $slugger,
        #[Autowire('%kernel.enabled_locales%')]
        private readonly array $localesActives,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('locale', ChoiceType::class, [
                'label' => 'Langue',
                'choices' => array_combine(array_map('strtoupper', $this->localesActives), $this->localesActives),
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'empty_data' => '',
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'empty_data' => '',
                'help' => 'Laisser vide pour le générer depuis le nom.',
            ]);

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $donnees = $event->getData();
            if (\is_array($donnees) && '' === trim((string) ($donnees['slug'] ?? '')) && '' !== trim((string) ($donnees['nom'] ?? ''))) {
                $donnees['slug'] = $this->slugger->slug((string) $donnees['nom'])->lower()->toString();
                $event->setData($donnees);
            }
        });
    }
}
