## ExoBooking Core

Plugin WordPress para gerenciamento de reservas com proteção anti-overbooking.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue.svg?style=flat-square)
![WordPress](https://img.shields.io/badge/WordPress-6.x-blue.svg?style=flat-square)
![MySQL](https://img.shields.io/badge/MySQL-8.0-blue.svg?style=flat-square)
![Docker](https://img.shields.io/badge/Docker-enabled-blue.svg?style=flat-square)
![License](https://img.shields.io/badge/License-MIT-green.svg?style=flat-square)

---

## Sobre o Projeto

O "ExoBooking Core" é um plugin WordPress desenvolvido para resolver um problema crítico em sistemas de reservas: o **overbooking** causado por "race conditions" (condições de corrida). Em cenários onde múltiplas requisições tentam reservar o mesmo recurso simultaneamente, sistemas mal projetados podem vender mais vagas do que o disponível. Este plugin implementa uma lógica robusta de transações de banco de dados com bloqueio (`SELECT ... FOR UPDATE`) para garantir a integridade do estoque, aprovando apenas as reservas que respeitam a capacidade real.

---

## Funcionalidades

*   **Custom Post Type "Passeios"**: Gerenciamento de passeios diretamente no painel do WordPress.
*   **Meta Box de Vagas por Data**: Interface intuitiva no CPT "Passeios" para definir a disponibilidade de vagas por dia para cada passeio.
*   **Proteção Anti-Overbooking**: Lógica de negócio implementada via REST API que utiliza transações MySQL com `SELECT ... FOR UPDATE` para prevenir que requisições simultâneas vendam mais vagas do que o estoque real.
*   **REST API Dedicada**: Endpoints para consulta de estoque e realização de reservas, permitindo integração com sistemas externos.
*   **Painel Administrativo Simples**: Tela no wp-admin para listar e visualizar todas as reservas realizadas.
*   **Dockerized Environment**: Ambiente de desenvolvimento completo e isolado via `docker-compose`, facilitando a configuração e o teste.

---

## Stack

*   **Linguagem**: PHP 7.4+
*   **Plataforma**: WordPress 6.x
*   **Banco de Dados**: MySQL 8.0 (InnoDB)
*   **Ferramentas**: Docker, Docker Compose
*   **Padrões**: `$wpdb` (nativo do WordPress), REST API

---

## Estrutura do Projeto

```
exobooking-test/
├── docker-compose.yml
├── .env (não versionado)
├── .gitignore
├── config/
│   └── apache.conf
├── tests/
│   └── concurrency-test.php
└── wp-content/
    └── plugins/
        └── exobooking-core/
            ├── exobooking-core.php
            ├── includes/
            │   ├── class-database.php
            │   ├── class-post-type.php
            │   └── class-api.php
            └── admin/
                ├── class-admin.php
                └── class-meta-box.php
```

---

## Pré-requisitos

*   Docker Desktop instalado e em execução.

---

## Instalação

Siga os passos para configurar o ambiente e o plugin:

1.  **Clonar o repositório:**
    ```bash
    git clone https://github.com/rodriguestg/exobooking.git
    cd exobooking
    

2.  **Criar o arquivo `.env`:**
    Edite o arquivo `.env.example` na raiz do projeto para `.env` com as seguintes variáveis:
    ```
    MYSQL_ROOT_PASSWORD=root
    MYSQL_DATABASE=wordpress
    MYSQL_USER=wp
    MYSQL_PASSWORD=wp123
    WP_DEBUG=1
    WP_DEBUG_LOG=1
    ```

3.  **Subir os containers Docker:**
    ```bash
    docker-compose up -d
    ```

4.  **Instalar o WordPress:**
    Acesse `http://localhost:8000` no seu navegador. Siga as instruções de instalação do WordPress.
    > **Sugestão:** Título do Site: `ExoBooking Test`, Nome de Usuário: `admin`, Senha: `Admin@2026`.

5.  **Configurar Links Permanentes:**
    No painel administrativo do WordPress (`http://localhost:8000/wp-admin`), vá em **Configurações → Links Permanentes**. Selecione a opção **"Nome do post"** e clique em **"Salvar alterações"**.
    > **Importante:** Este passo é crítico para que a REST API funcione corretamente.

6.  **Ativar o plugin:**
    No painel administrativo, vá em **Plugins**. Localize "ExoBooking Core" e clique em **"Ativar"**.

---

## Criando um Passeio

Para testar o sistema de reservas, você precisa criar um passeio e definir sua disponibilidade:

1.  No painel administrativo, vá em **Passeios → Adicionar Novo**.
2.  Preencha o **Título** do passeio (ex: "Trilha da Serra").
3.  Role a página até encontrar o meta box **"Disponibilidade de Vagas"**.
4.  Defina uma **Data** (ex: `2026-03-20`) e a **Quantidade de Vagas** (ex: `3`).
5.  Clique em **"Publicar"** para salvar o passeio e suas vagas.

---

## API Endpoints

O plugin expõe os seguintes endpoints REST para gerenciamento de reservas:

### `GET /inventory`

Consulta o estoque atual de vagas para todos os passeios e datas.

*   **Método:** `GET`
*   **URL:** `http://localhost:8000/wp-json/exobooking/v1/inventory`
*   **Descrição:** Retorna uma lista de todas as disponibilidades de vagas cadastradas.

### `POST /bookings`

Realiza uma reserva para um passeio específico.

*   **Método:** `GET`
*   **URL:** `http://localhost:8000/wp-json/exobooking/v1/bookings`
*   **Body:** `{"passeio_id":5,"customer_name":"Gabriel","customer_email":"g@test.com","booking_date":"2026-03-20","quantity":1}`
*   **Descrição:** Realiza uma reserva para um passeio.


Status | Código | Descrição

200 OK success: true -> Reserva confirmada com sucesso.
400 Bad Requestinvalid_date -> Formato de data inválido.
400 Bad Requestrest_missing_callback_param -> Parâmetros obrigatórios faltando.
404 Not Foundnot_found -> Passeio ou data não encontrado.
409 Conflictno_availability -> Vagas insuficientes para a reserva.
500 Internal Server Errordb_error -> Erro interno ao processar a reserva.


---

## Teste de Concorrência (Anti-Overbooking)

Este teste demonstra a eficácia do mecanismo anti-overbooking do plugin.

### Como funciona

O plugin utiliza transações MySQL com `SELECT FOR UPDATE`. Isso significa que, ao tentar reservar vagas, a linha do banco de dados referente ao estoque é **bloqueada** temporariamente. Requisições simultâneas que tentarem acessar a mesma linha aguardarão na fila até que a transação atual seja concluída (commit ou rollback). Isso elimina as *race conditions* e garante que o estoque nunca seja excedido.

### Executando o teste

Vamos simular 5 tentativas de reserva para um passeio com apenas 3 vagas disponíveis.

1.  **Resetar o estoque e reservas:**
    ```powershell
    $DB_PASS = "root"
    docker exec exobooking_db mysql -uroot -p"$DB_PASS" wordpress -e "DELETE FROM wp_exobooking_bookings; UPDATE wp_exobooking_inventory SET available_slots = 3 WHERE passeio_id = 5;"
    ```

2.  **Executar o script de concorrência:**
    ```powershell
    docker cp tests\concurrency-test.php exobooking_wp:/tmp/concurrency-test.php
    docker exec exobooking_wp php /tmp/concurrency-test.php 5
    ```

**Resultado esperado:**
```text
  ExoBooking — Teste de Concorrência
  Endpoint : http://localhost:80/wp-json/exobooking/v1/bookings
  Passeio  : #5

  Estoque antes: 3/3 vagas disponíveis

Disparando 5 requisições simultâneas...

Requisição 1: [409] ❌ BLOQUEADA → Vagas insuficientes. Disponível: 0
Requisição 2: [200] ✅ APROVADA  → booking_id=3, vagas restantes=2
Requisição 3: [200] ✅ APROVADA  → booking_id=4, vagas restantes=1
Requisição 4: [200] ✅ APROVADA  → booking_id=5, vagas restantes=0
Requisição 5: [409] ❌ BLOQUEADA → Vagas insuficientes. Disponível: 0

  RESULTADO FINAL
  Aprovadas : 3 (esperado: 3)
  Bloqueadas: 2 (esperado: 2)

✅ PASSOU — Sistema anti-overbooking funcionando corretamente!
```

---

## Painel Administrativo

Acesse `http://localhost:8000/wp-admin`. No menu lateral, clique em **"ExoBooking"** para visualizar a listagem de todas as reservas realizadas, incluindo ID, Cliente, Passeio, Data e Status.

---

## Decisões Técnicas

*   **Por que `$wpdb` e não Eloquent?**
    Utiliza a classe `$wpdb` nativa do WordPress para evitar dependências externas e manter a simplicidade. Ela oferece suporte completo a transações e `prepare()` para segurança.

*   **Por que `SELECT FOR UPDATE`?**
    Essencial para o mecanismo anti-overbooking. Ele bloqueia a linha do estoque no banco de dados durante a transação, impedindo que outras requisições leiam dados desatualizados e garantindo a consistência.

*   **Por que MySQL e não MongoDB?**
    O WordPress é construído sobre MySQL (ou MariaDB). Utilizar MongoDB introduziria uma camada de complexidade desnecessária e incompatibilidade fundamental com o core do WordPress e suas ferramentas (`$wpdb`). A escolha do MySQL garante a integração nativa e o uso de recursos como transações e bloqueios de linha, essenciais para o anti-overbooking.
    
---

## Solução de Problemas

*   **404 nos endpoints da API:**
    Verifique se os Links Permanentes estão configurados como **"Nome do post"** em **Configurações → Links Permanentes** no wp-admin.

*   **Tabelas não criadas no banco:**
    No painel de Plugins do WordPress, **desative** e **ative** o plugin "ExoBooking Core" novamente. Isso forçará a execução do hook de ativação que cria as tabelas.

---

## Licença

MIT License — **Gabriel Rodrigues** — [github.com/rodriguestg](https://github.com/rodriguestg)
