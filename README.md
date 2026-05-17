# Facturación Electrónica SUNAT — API (Greenter + Laravel 12)

API REST en **Laravel 12 / PHP 8.3** para la generación, firma y envío de comprobantes electrónicos (facturas, boletas, notas) a **SUNAT** (Perú), construida sobre el ecosistema [Greenter](https://greenter.dev). Multi-tenant: cada usuario gestiona sus propias empresas y comprobantes.

## Características

- Registro y autenticación con **Laravel Sanctum** (tokens Bearer).
- CRUD de **empresas** por usuario (clave de ruta: `ruc`).
- Generación de **XML UBL 2.1** firmado digitalmente.
- Envío directo a los endpoints SOAP de SUNAT (beta y producción).
- Generación de **PDF/HTML** del comprobante.
- Conversión automática de montos a letras (es-PE) para la leyenda legal.
- Cálculo de totales por tipo de afectación IGV (Catálogo 07 SUNAT: 10 Gravado, 20 Exonerado, 30 Inafecto, 40 Exportación, etc.).

## Stack

| Capa | Tecnología |
|---|---|
| Framework | Laravel 12 |
| PHP | 8.3 |
| Auth | Laravel Sanctum 4 |
| Facturación electrónica | `greenter/lite`, `greenter/report`, `greenter/htmltopdf` |
| PDF | `barryvdh/laravel-dompdf` |
| Montos a letras | `luecano/numero-a-letras` |
| DB | MySQL (dev local: Laragon) / SQLite in-memory en tests |
| Tests | PHPUnit 11 |
| Lint/format | Laravel Pint (PSR-12) |

## Requisitos

- PHP **8.3+**
- Composer 2
- Node.js 20+ y npm
- MySQL 8 (o el motor configurado en `.env`)
- Extensiones PHP: `openssl`, `soap`, `mbstring`, `xml`, `curl`, `pdo_mysql`

## Instalación

```bash
git clone git@github.com:CarlosESantaella/facturacion-greenter.git
cd facturacion-greenter
composer run setup
```

El script `setup` ejecuta: `composer install`, copia `.env.example` → `.env`, genera la app key, corre migraciones, instala npm y compila assets.

Configura las credenciales de base de datos en `.env` antes de ejecutar `setup`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=greenter
DB_USERNAME=root
DB_PASSWORD=
```

## Comandos

| Tarea | Comando |
|---|---|
| Setup completo | `composer run setup` |
| Servidor de desarrollo (Laravel + queue + Vite) | `composer run dev` |
| Correr todos los tests | `composer run test` |
| Correr un test específico | `php artisan test --filter=NombreDelTest` |
| Lint / format | `./vendor/bin/pint` |
| Migraciones | `php artisan migrate` |
| Build de assets | `npm run build` |

## Endpoints

Base: `/api`

### Públicos

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/register` | Registrar usuario |
| POST | `/login` | Iniciar sesión y obtener token |

### Autenticados (`Authorization: Bearer <token>`)

| Método | Ruta | Descripción |
|---|---|---|
| POST | `/logout` | Cerrar sesión (revocar token) |
| POST | `/refresh` | Renovar token |
| POST | `/me` | Datos del usuario autenticado |
| GET | `/user` | Usuario actual (Sanctum) |
| GET\|POST\|PUT\|DELETE | `/companies` | CRUD de empresas (route key: `{ruc}`) |
| GET | `/invoices/send` | Firmar y enviar comprobante a SUNAT |
| GET | `/invoices/xml` | Obtener el XML UBL del comprobante |
| GET | `/invoices/pdf` | Obtener el PDF del comprobante |

## Estructura

```
app/
├── Http/Controllers/Api/
│   ├── AuthController.php
│   ├── RegisterController.php
│   ├── CompanyController.php
│   └── InvoiceController.php       # incluye setTotales() con lógica IGV cat. 07
├── Models/
│   ├── User.php                    # HasApiTokens
│   └── Company.php                 # belongsTo User, ruc como route key
├── Rules/
│   └── UniqueRucRule.php           # RUC único por usuario
└── Services/
    └── SunatService.php            # integración con Greenter (See, Invoice, Client, ...)

storage/app/
├── logos/                          # logos de empresa
└── certs/                          # certificados .pfx/.pem (no se versionan)
```

## Almacenamiento de archivos sensibles

Los certificados digitales (`.pfx` / `.pem`) y logos de empresa se guardan en `storage/app/certs/` y `storage/app/logos/` respectivamente. **No subir** estos archivos al repositorio: están excluidos vía `.gitignore`.

## Convenciones

- **PSR-12** vía Laravel Pint, 4 espacios de indentación.
- Nombres en **español** para campos fiscales/de negocio (`razon_social`, `ruc`, `tipoDoc`, `mtoIGV`, `sol_user`); en **inglés** para patrones de framework.
- Validación inline en controllers con `$request->validate()` (sin Form Requests).
- Redondeo de totales: `floor(x * 10) / 10`.

## Tests

```bash
composer run test
```

PHPUnit 11 con SQLite en memoria (`phpunit.xml` sobrescribe la DB a `sqlite/:memory:`).

## Licencia

MIT.
