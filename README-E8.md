# Address API (E8)

Run (PHP built-in server):
```bash
php -S 127.0.0.1:8080 -t public
```
Env:
- DB_DSN (default: sqlite:./tools/index/address-index.sqlite)
- DB_USER / DB_PASS (if using MySQL)
- NET=1 to enable live geocoding

cURL:
```bash
curl -s http://127.0.0.1:8080/api/address/index/search?prefix=Hou&country=US
curl -s -X POST http://127.0.0.1:8080/api/address/validate -H 'Content-Type: application/json' -d '{"line1":"123 Main St","city":"Houston","region":"TX","postal":"77002","country":"US"}'
curl -s -X POST http://127.0.0.1:8080/api/address/parse -H 'Content-Type: application/json' -d '{"text":"123 Main St, Houston, TX 77002, USA"}'
NET=1 curl -s -X POST http://127.0.0.1:8080/api/address/geocode -H 'Content-Type: application/json' -d '{"line1":"10 Downing St","city":"London","postal":"SW1A 2AA","country":"GB"}'
```
