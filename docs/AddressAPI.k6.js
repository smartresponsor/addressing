/*
 * Copyright (c) 2025 Oleksandr Tishchenko / Marketing America Corp
 */
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  vus: 10, // виртуальных пользователей
  duration: '30s', // продолжительность теста
};

export default function () {
  // Тестируем создание адреса
  let createRes = http.post('http://localhost:8000/address', JSON.stringify({
    city: 'Kyiv',
    country: 'UA',
    street: 'Khreshchatyk',
    zipcode: '01001'
  }), { headers: { 'Content-Type': 'application/json' } });

  check(createRes, {
    'create status is 201': (r) => r.status === 201,
  });

  // Тестируем получение по ID
  let getRes = http.get('http://localhost:8000/address/1');
  check(getRes, {
    'get status is 200 or 404': (r) => r.status === 200 || r.status === 404,
  });

  // Тестируем поиск по городу
  let searchRes = http.get('http://localhost:8000/address/search?city=Kyiv');
  check(searchRes, {
    'search status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
