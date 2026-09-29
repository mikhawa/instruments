<?php

declare(strict_types=1);

namespace App\Form\Admin;

use App\Entity\CategorieTraduction;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Traduction d'une catégorie dans le back-office.
 */
final class CategorieTraductionType extends AbstractTraductionType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('description', TextareaType::class, [
            'label' => 'Description',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => CategorieTraduction::class]);
    }
}
