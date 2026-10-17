<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\InstrumentTraduction;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Traduction d'un instrument dans le back-office.
 */
final class InstrumentTraductionType extends AbstractTraductionType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('descriptionCourte', TextareaType::class, [
                'label' => 'Description courte',
                'required' => false,
                'attr' => ['rows' => 2, 'maxlength' => 300],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 6],
            ])
            ->add('materiaux', TextType::class, [
                'label' => 'Matériaux',
                'required' => false,
            ])
            ->add('histoire', TextareaType::class, [
                'label' => 'Histoire et contexte culturel',
                'required' => false,
                'attr' => ['rows' => 6],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InstrumentTraduction::class]);
    }
}
