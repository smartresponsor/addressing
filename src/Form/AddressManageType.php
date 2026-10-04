<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Addressing\Form;

use App\Addressing\DTO\AddressManageDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Defines the standalone Addressing management form used to capture canonical address input values.
 *
 * @extends AbstractType<AddressManageDTO>
 */
final class AddressManageType extends AbstractType
{
    /**
     * Builds the address management input fields and submit action for the standalone Addressing form.
     */
    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

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

    /**
     * Binds the management form to the Addressing input DTO used by the standalone create flow.
     */
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefaults([
            'data_class' => AddressManageDTO::class,
        ]);
    }
}
