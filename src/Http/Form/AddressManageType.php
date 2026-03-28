<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Form;

use App\Http\Dto\AddressManageDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class AddressManageType extends AbstractType
{
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('line1', TextType::class, ['label' => 'Address line 1'])
            ->add('line2', TextType::class, ['label' => 'Address line 2', 'required' => false])
            ->add('city', TextType::class)
            ->add('region', TextType::class, ['required' => false])
            ->add('postalCode', TextType::class, ['required' => false])
            ->add('countryCode', CountryType::class, [
                'label' => 'Country',
                'required' => true,
                'preferred_choices' => ['US', 'CA', 'GB'],
            ])
            ->add('ownerId', TextType::class, ['required' => false])
            ->add('vendorId', TextType::class, ['required' => false])
            ->add('save', SubmitType::class, ['label' => 'Create address']);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressManageDto::class,
        ]);
    }
}
