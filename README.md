# ExoBooking Core

Plugin WordPress para gerenciamento de reservas com proteção anti-overbooking.

## Como rodar

1. Clone o repositório
2. Execute: docker-compose up -d
3. Acesse http://localhost:8000 e instale o WordPress
4. Vá em Plugins e ative o ExoBooking Core

## Teste de Concorrencia

Dispare 5 requisições simultâneas para:
POST http://localhost:8000/wp-json/exobooking/v1/bookings

Esperado: 3 aprovadas, 2 bloqueadas com erro de vagas esgotadas.

## Stack

- PHP 7.4+
- WordPress 6.x
- MySQL 8.0 (InnoDB)
- Docker + docker-compose
