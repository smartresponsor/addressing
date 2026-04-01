# Address business flows

Short, business-focused flows with input/output trace for the Address domain.

## Create address

**Input:** API request payload with address fields (`line1`, `city`, `countryCode`, optional `dedupeKey`).  
**Process:** `App\Http\AddressApi\Controller::create` builds `AddressData` and
calls `App\Service\Application\Address\AddressService::create`, which persists
via `App\Repository\Address\AddressRepository::create` and appends `AddressCreated` to `address_outbox`.  
**Output:** `{ "id": "<ulid>" }` response + outbox row.

## Update address

**Input:** updated address payload + id.  
**Process:** `AddressService::update` calls `AddressRepository::update`, which updates the row and
appends `AddressUpdated` to `address_outbox`.  
**Output:** updated row in `address_entity` + outbox row.

## Search (page)

**Input:** query filters (`ownerId`, `vendorId`, `countryCode`, `q`, `cursor`, `limit`).  
**Process:** `AddressService::search` delegates to `AddressRepository::findPage` and returns paged results.  
**Output:** `{ items: [...], nextCursor }`.

## Dedupe lookup

**Input:** normalized `dedupeKey`.  
**Process:** `AddressService::dedupe` calls `AddressRepository::findByDedupeKey` to return a matching address (if
any).  
**Output:** existing address or `null`.

## Outbox event delivery

**Input:** target webhook URL + drain parameters (`limit`, `retryLimit`, `timeoutSec`, `backoffMs`).  
**Process:** `App\Service\Application\Address\AddressOutboxDrainer::drain` reads `address_outbox` rows and POSTs each event
payload.  
**Output:** published rows updated with `published_at`, or `last_error` filled on failure.
