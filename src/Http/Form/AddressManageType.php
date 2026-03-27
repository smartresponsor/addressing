<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Form;

use App\Http\Dto\AddressManageDto;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

final class AddressManageType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('ownerId', TextType::class, ['required' => false, 'label' => 'Owner ID'])
            ->add('vendorId', TextType::class, ['required' => false, 'label' => 'Vendor ID'])
            ->add('line1', TextType::class, [
                'label' => 'Street line 1',
                'constraints' => [new NotBlank(), new Length(min: 2, max: 256)],
            ])
            ->add('line2', TextType::class, ['required' => false, 'label' => 'Street line 2'])
            ->add('city', TextType::class, [
                'constraints' => [new NotBlank(), new Length(min: 2, max: 120)],
            ])
            ->add('region', TextType::class, ['required' => false])
            ->add('postalCode', TextType::class, [
                'required' => false,
                'constraints' => [new Length(min: 3, max: 32)],
            ])
            ->add('countryCode', CountryType::class, [
                'label' => 'Country',
                'placeholder' => false,
            ])
            ->add('save', SubmitType::class, ['label' => 'Create Address']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => AddressManageDto::class,
            'csrf_protection' => false,
        ]);
    }
}
