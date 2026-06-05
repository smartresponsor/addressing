<?php

// Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Service\Http\Address;

use App\EntityInterface\Record\AddressInterface;
use App\Http\Dto\AddressInputFactory;
use App\Http\Dto\AddressManageDto;
use App\Http\Factory\AddressViewArrayFactory;
use App\Http\Form\AddressManageType;
use App\Service\Application\AddressReadService;
use App\Service\Application\AddressWriteService;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;
use Twig\Environment;

final readonly class AddressManageHttpService
{
    public function __construct(
        private FormFactoryInterface $formFactory,
        private AddressInputFactory $addressInputFactory,
        private AddressViewArrayFactory $addressViewArrayFactory,
        private AddressReadService $addressReadService,
        private AddressWriteService $addressWriteService,
        private Environment $twig,
    ) {
    }

    /**
     * Renders the management surface and previews matching rows.
     *
     * @throws \Throwable
     */
    public function manage(Request $request): Response
    {
        $createdAddressId = null;
        $form = $this->formFactory->create(AddressManageType::class, new AddressManageDto());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dto = $form->getData();
            if ($dto instanceof AddressManageDto) {
                $createdAddressId = $this->createFromManageDto($dto);
            }
        }

        $previewRows = $form->getData() instanceof AddressManageDto
            ? $this->previewRows($form->getData())
            : [];

        return new Response($this->twig->render('address/manage.html.twig', [
            'manageForm' => $form->createView(),
            'createdId' => $createdAddressId,
            'previewRows' => $previewRows,
        ]));
    }

    private function createFromManageDto(AddressManageDto $addressManageDto): string
    {
        $addressData = $this->addressInputFactory->fromManageDto($addressManageDto, [
            'id' => (string) new Ulid(),
            'createdAt' => $this->currentTimestampLiteral(),
            'sourceSystem' => 'symfony-manage',
            'sourceType' => 'manual',
            'sourceReference' => 'manage-form',
        ]);
        $this->addressWriteService->create($addressData);

        return $addressData->id();
    }

    private function currentTimestampLiteral(): string
    {
        $now = new \DateTimeImmutable('now');

        return $now->format('Y-m-d H:i:sP');
    }

    /** @return array<int, array<string, mixed>> */
    private function previewRows(AddressManageDto $addressManageDto): array
    {
        $ownerId = $this->nullableFormString(['ownerId' => $addressManageDto->ownerId], 'ownerId');
        $vendorId = $this->nullableFormString(['vendorId' => $addressManageDto->vendorId], 'vendorId');
        if (null === $ownerId && null === $vendorId) {
            return [];
        }

        return array_map(
            fn (AddressInterface $address): array => $this->addressViewArrayFactory->previewRow($address),
            $this->addressReadService->search($ownerId, $vendorId, null, null, 10, null)['items']
        );
    }

    /** @param array<string, mixed> $payload */
    private function nullableFormString(array $payload, string $key): ?string
    {
        if (!array_key_exists($key, $payload) || null === $payload[$key]) {
            return null;
        }
        if (!is_scalar($payload[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }

        $value = trim((string) $payload[$key]);

        return '' === $value ? null : $value;
    }
}
