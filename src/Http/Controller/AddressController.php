<?php
# Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
declare(strict_types=1);

namespace App\Http\Controller;

use App\Contract\Message\AddressValidated;
use App\Entity\Record\AddressData;
use App\EntityInterface\Record\AddressInterface;
use App\Http\Dto\AddressManageDto;
use App\Http\Form\AddressManageType;
use App\Repository\Persistence\AddressRepository;
use App\Service\Application\AddressValidatedApplierService;
use App\Value\CountryCode;
use App\Value\PostalCode;
use App\Value\StreetLine;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\Extension\Core\CoreExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\FormRendererInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

final class AddressController
{
    public function __construct(
        private readonly AddressRepository $repo,
        private readonly AddressValidatedApplierService $validatedApplier,
        private readonly FormFactoryInterface $formFactory,
        private readonly Environment $twig,
    ) {
    }

    public static function fromPg(\PDO $pg): self
    {
        $validator = Validation::createValidator();
        $twig = self::createTwigEnvironment();
        $formFactory = self::createFormFactory($twig, $validator);

        return new self(
            new AddressRepository($pg),
            new AddressValidatedApplierService($pg),
            $formFactory,
            $twig,
        );
    }

    public function manage(Request $request): Response
    {
        $dto = new AddressManageDto();
        $form = $this->formFactory->create(AddressManageType::class, $dto);
        $form->handleRequest($request);

        $createdId = null;
        if ($form->isSubmitted() && $form->isValid()) {
            $address = $this->dtoToAddress($dto);
            $this->repo->create($address);
            $createdId = $address->id();
        }

        return new Response(
            $this->twig->render('address/manage.html.twig', [
                'form' => $form->createView(),
                'createdId' => $createdId,
            ])
        );
    }

    public function create(Request $req): JsonResponse
    {
        $in = self::json($req);

        $id = (string) new Ulid();
        $now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP');

        $address = new AddressData(
            $id,
            self::optStr($in, 'ownerId'),
            self::optStr($in, 'vendorId'),
            self::reqStr($in, 'line1'),
            self::optStr($in, 'line2'),
            self::reqStr($in, 'city'),
            self::optStr($in, 'region'),
            self::optStr($in, 'postalCode'),
            strtoupper(self::reqStr($in, 'countryCode')),
            self::optStr($in, 'line1Norm'),
            self::optStr($in, 'cityNorm'),
            self::optStr($in, 'regionNorm'),
            self::optStr($in, 'postalCodeNorm'),
            self::optFloat($in, 'latitude'),
            self::optFloat($in, 'longitude'),
            self::optStr($in, 'geohash'),
            self::optStr($in, 'validationStatus') ?? 'pending',
            self::optStr($in, 'validationProvider'),
            self::optStr($in, 'validatedAt'),
            self::optStr($in, 'dedupeKey'),
            $now,
            null,
            null
        );

        $this->repo->create($address);

        return new JsonResponse(['id' => $id], 201);
    }

    public function get(Request $req, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address));
    }

    public function markDeleted(Request $req, string $id): JsonResponse
    {
        [$ownerId, $vendorId] = self::tenantFromQuery($req);
        $this->repo->markDeleted($id, $ownerId, $vendorId);

        return new JsonResponse(['ok' => true]);
    }

    public function page(Request $req): JsonResponse
    {
        $limit = (int) ($req->query->get('limit') ?? 25);
        if ($limit < 1) {
            $limit = 1;
        }
        if ($limit > 200) {
            $limit = 200;
        }

        $cursor = self::queryStringOrNull($req, 'cursor');
        $ownerId = self::queryStringOrNull($req, 'ownerId');
        $vendorId = self::queryStringOrNull($req, 'vendorId');
        $countryCode = self::queryStringOrNull($req, 'countryCode');
        $countryCode = null !== $countryCode ? strtoupper($countryCode) : null;
        $q = self::queryStringOrNull($req, 'q');

        $res = $this->repo->findPage($ownerId, $vendorId, $countryCode, $q, $limit, $cursor);

        $items = array_map(fn (AddressInterface $address): array => self::toArray($address), $res['items']);

        return new JsonResponse([
            'items' => $items,
            'nextCursor' => $res['nextCursor'],
        ]);
    }

    public function applyValidated(Request $req, string $id): JsonResponse
    {
        $in = self::json($req);
        [$ownerId, $vendorId] = self::tenantFromQuery($req);

        $validated = AddressValidated::fromArray([
            'line1Norm' => self::optStr($in, 'line1Norm'),
            'cityNorm' => self::optStr($in, 'cityNorm'),
            'regionNorm' => self::optStr($in, 'regionNorm'),
            'postalCodeNorm' => self::optStr($in, 'postalCodeNorm'),
            'latitude' => self::optFloat($in, 'latitude'),
            'longitude' => self::optFloat($in, 'longitude'),
            'geohash' => self::optStr($in, 'geohash'),
            'validationProvider' => self::optStr($in, 'provider') ?? self::optStr($in, 'validationProvider'),
            'validatedAt' => self::optStr($in, 'validatedAt'),
            'dedupeKey' => self::optStr($in, 'dedupeKey'),
        ]);

        $this->validatedApplier->apply($id, $validated, $ownerId, $vendorId);

        $address = $this->repo->get($id, $ownerId, $vendorId);
        if (null === $address) {
            return new JsonResponse(['error' => 'not_found'], 404);
        }

        return new JsonResponse(self::toArray($address));
    }

    private static function createTwigEnvironment(): Environment
    {
        $loader = new FilesystemLoader(dirname(__DIR__, 3).'/templates');

        return new Environment($loader);
    }

    private static function createFormFactory(Environment $twig, ValidatorInterface $validator): FormFactoryInterface
    {
        $formEngine = self::createFormRendererEngine($twig);
        $formRenderer = new FormRenderer($formEngine);
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRendererInterface => $formRenderer,
        ]));
        $twig->addExtension(new FormExtension());

        return Forms::createFormFactoryBuilder()
            ->addExtension(new CoreExtension())
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new ValidatorExtension($validator))
            ->addType(new AddressManageType())
            ->getFormFactory();
    }

    private static function createFormRendererEngine(Environment $twig): TwigRendererEngine
    {
        return new TwigRendererEngine(['form_div_layout.html.twig'], $twig);
    }

    private function dtoToAddress(AddressManageDto $dto): AddressData
    {
        $line1 = (new StreetLine($dto->line1))->value();
        $countryCode = (new CountryCode($dto->countryCode))->value();
        $postalCode = $dto->postalCode !== null && $dto->postalCode !== ''
            ? (new PostalCode($dto->postalCode))->value()
            : null;

        return new AddressData(
            (string) new Ulid(),
            self::trimOrNull($dto->ownerId),
            self::trimOrNull($dto->vendorId),
            $line1,
            self::trimOrNull($dto->line2),
            trim($dto->city),
            self::trimOrNull($dto->region),
            $postalCode,
            $countryCode,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            'pending',
            null,
            null,
            null,
            (new \DateTimeImmutable('now'))->format('Y-m-d H:i:sP'),
            null,
            null,
        );
    }

    private static function trimOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /** @return array<string, mixed> */
    private static function json(Request $req): array
    {
        $raw = $req->getContent();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            throw new \RuntimeException('invalid_json');
        }

        return $data;
    }

    /** @param array<string, mixed> $in */
    private static function reqStr(array $in, string $key): string
    {
        if (!array_key_exists($key, $in) || !is_string($in[$key]) || '' === trim($in[$key])) {
            throw new \RuntimeException('missing_'.$key);
        }

        return trim($in[$key]);
    }

    /** @param array<string, mixed> $in */
    private static function optStr(array $in, string $key): ?string
    {
        if (!array_key_exists($key, $in) || null === $in[$key]) {
            return null;
        }
        if (!is_string($in[$key])) {
            throw new \RuntimeException('invalid_'.$key);
        }
        $v = trim($in[$key]);

        return '' === $v ? null : $v;
    }

    /** @return array{0: ?string, 1: ?string} */
    private static function tenantFromQuery(Request $req): array
    {
        $ownerId = self::queryStringOrNull($req, 'ownerId');
        $vendorId = self::queryStringOrNull($req, 'vendorId');

        return [$ownerId, $vendorId];
    }

    private static function queryStringOrNull(Request $req, string $key): ?string
    {
        $value = $req->query->get($key);

        return is_string($value) && '' !== $value ? $value : null;
    }

    /** @param array<string, mixed> $in */
    private static function optFloat(array $in, string $key): ?float
    {
        if (!array_key_exists($key, $in) || null === $in[$key] || '' === $in[$key]) {
            return null;
        }
        if (is_int($in[$key]) || is_float($in[$key])) {
            return (float) $in[$key];
        }
        if (is_string($in[$key]) && is_numeric($in[$key])) {
            return (float) $in[$key];
        }
        throw new \RuntimeException('invalid_'.$key);
    }

    /** @return array<string, mixed> */
    private static function toArray(AddressInterface $address): array
    {
        return [
            'id' => $address->id(),
            'ownerId' => $address->ownerId(),
            'vendorId' => $address->vendorId(),
            'line1' => $address->line1(),
            'line2' => $address->line2(),
            'city' => $address->city(),
            'region' => $address->region(),
            'postalCode' => $address->postalCode(),
            'countryCode' => $address->countryCode(),
            'line1Norm' => $address->line1Norm(),
            'cityNorm' => $address->cityNorm(),
            'regionNorm' => $address->regionNorm(),
            'postalCodeNorm' => $address->postalCodeNorm(),
            'latitude' => $address->latitude(),
            'longitude' => $address->longitude(),
            'geohash' => $address->geohash(),
            'validationStatus' => $address->validationStatus(),
            'validationProvider' => $address->validationProvider(),
            'validatedAt' => $address->validatedAt(),
            'dedupeKey' => $address->dedupeKey(),
            'createdAt' => $address->createdAt(),
            'updatedAt' => $address->updatedAt(),
            'deletedAt' => $address->deletedAt(),
        ];
    }
}
