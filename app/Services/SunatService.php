<?php

namespace App\Services;

use App\Models\Company;
use DateTime;
use Greenter\Model\Client\Client as GreenterClient;
use Greenter\Model\Company\Address as GreenterAddress;
use Greenter\Model\Company\Company as GreenterCompany;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado as GreenterFormaPagoContado;
use Greenter\Model\Sale\Invoice as GreenterInvoice;
use Greenter\Model\Sale\Legend as GreenterLegend;
use Greenter\Model\Sale\SaleDetail as GreenterSaleDetail;
use Barryvdh\DomPDF\Facade\Pdf;
use Greenter\Report\HtmlReport;
use Greenter\Report\Resolver\DefaultTemplateResolver;
use Greenter\See;
use Greenter\Ws\Services\SunatEndpoints;
use Illuminate\Support\Facades\Storage;

class SunatService
{

    public function getSee($company)
    {
        $certificate = Storage::get($company->cert_path);

        $see = new See();
        $see->setCertificate($certificate);
        $see->setService($company->production ? SunatEndpoints::FE_PRODUCCION : SunatEndpoints::FE_BETA);
        $see->setClaveSOL($company->ruc, $company->sol_user, $company->sol_pass);
        return $see;
    }

    public function getInvoice($data)
    {
        return (new GreenterInvoice())
            ->setUblVersion($data['ublVersion'] ?? '2.1') // UBL Version - Catalog. 2
            ->setTipoOperacion($data['tipoOperacion'] ?? null) // Venta - Catalog. 51
            ->setTipoDoc($data['tipoDoc'] ?? null) // Factura - Catalog. 01
            ->setSerie($data['serie'] ?? null) // Serie del comprobante
            ->setCorrelativo($data['correlativo'] ?? null) // Correlativo del comprobante
            ->setFechaEmision(new DateTime($data['fechaEmision']) ?? null) // Zona horaria: Lima
            ->setFormaPago(new GreenterFormaPagoContado()) // FormaPago: Contado
            ->setTipoMoneda($data['tipoMoneda'] ?? null) // Sol - Catalog. 02
            ->setCompany($this->getCompany($data['company'])) // Datos de la empresa emisora
            ->setClient($this->getClient($data['cliente'])) // Datos del cliente

            //mtoOper
            ->setMtoOperGravadas($data['mtoOperGravadas']) // Monto de operaciones gravadas
            ->setMtoOperExoneradas($data['mtoOperExoneradas']) // Monto de operaciones exoneradas
            ->setMtoOperInafectas($data['mtoOperInafectas']) // Monto de operaciones inafectas
            ->setMtoOperExportacion($data['mtoOperExportacion']) // Monto de operaciones de exportación
            ->setMtoOperGratuitas($data['mtoOperGratuitas']) // Monto de operaciones gratuitas

            //Impuestos
            ->setMtoIGV($data['mtoIGV']) // Monto del IGV
            ->setMtoIGVGratuitas($data['mtoIGVGratuitas']) // Monto del IGV de operaciones gratuitas
            ->setIcbper($data['icbper']) // Monto del ICBPER
            ->setTotalImpuestos($data['totalImpuestos']) // Monto total de impuestos

            //totales
            ->setValorVenta($data['valorVenta']) // Valor de venta
            ->setSubTotal($data['subTotal']) // Sub total
            ->setRedondeo($data['redondeo'] ?? 0) // Redondeo
            ->setMtoImpVenta($data['mtoImpVenta']) // Monto total a pagar

            //productos
            ->setDetails($this->getDetails($data['detalle'] ?? [])) // Detalles de la venta

            //leyendas
            ->setLegends($this->getLegends($data['leyendas'] ?? [])); // Leyendas
    }

    public function getClient($client)
    {
        return (new GreenterClient())
            ->setTipoDoc($client['tipoDoc'] ?? null) // Tipo de documento - Catalog. 06
            ->setNumDoc($client['numDoc'] ?? null) // Número de documento
            ->setRznSocial($client['rznSocial'] ?? null); // Razón social o nombres y apellidos;
    }

    public function getCompany($company)
    {
        return (new GreenterCompany())
            ->setRuc($company['ruc'] ?? null)
            ->setRazonSocial($company['razonSocial'] ?? null)
            ->setNombreComercial($company['nombreComercial'] ?? null)
            ->setAddress($this->getAddress($company['address']));
    }

    public function getAddress($address)
    {
        return (new GreenterAddress())
            ->setUbigueo($address['ubigueo'] ?? null) // Ubigeo - Catalog. 06
            ->setDepartamento($address['departamento'] ?? null)
            ->setProvincia($address['provincia'] ?? null)
            ->setDistrito($address['distrito'] ?? null)
            ->setUrbanizacion($address['urbanizacion'] ?? null)
            ->setDireccion($address['direccion'] ?? null)
            ->setCodLocal($address['codLocal'] ?? null); // Codigo de establecimiento asignado por SUNAT, 0000 por defecto.
    }

    public function getDetails($details)
    {
        $greenterDetails = [];

        foreach ($details as $detail) {
            $greenterDetails[] = (new GreenterSaleDetail())
                ->setCodProducto($detail['codProducto'] ?? null) // Código del producto o servicio
                ->setUnidad($detail['unidad'] ?? null) // Unidad - Catalog. 03
                ->setCantidad($detail['cantidad'] ?? null) // Cantidad
                ->setMtoValorUnitario($detail['mtoValorUnitario'] ?? null) // Monto valor unitario
                ->setDescripcion($detail['descripcion'] ?? null)
                ->setMtoBaseIgv($detail['mtoBaseIgv'] ?? null)
                ->setPorcentajeIgv($detail['porcentajeIgv'] ?? null) // 18%
                ->setIgv($detail['igv'] ?? null)
                ->setFactorIcbper($detail['factorIcbper'] ?? null) // Factor del ICBPER - Catalog. 48
                ->setIcbper($detail['icbper'] ?? null) // Monto del ICBPER
                ->setTipAfeIgv($detail['tipAfeIgv'] ?? null) // Gravado Op. Onerosa - Catalog. 07
                ->setTotalImpuestos($detail['totalImpuestos'] ?? null) // Suma de impuestos en el detalle
                ->setMtoValorVenta($detail['mtoValorVenta'] ?? null)
                ->setMtoPrecioUnitario($detail['mtoPrecioUnitario'] ?? null);
        }

        return $greenterDetails;
    }

    public function getLegends($legends): null|array
    {
        $greenLegends = [];

        foreach ($legends as $legend) {
            $greenLegends[] = (new GreenterLegend())
                ->setCode($legend['codigo'] ?? null) // Código de la leyenda - Catalog. 52
                ->setValue($legend['valor'] ?? null); // Valor de la leyenda
        }

        return $greenLegends;
    }

    public function sunatResponse($result)
    {
        $response['success'] = $result->isSuccess();

        if (!$response['success']) {
            $response['error'] = [
                'code' => $result->getError()->getCode(),
                'message' => $result->getError()->getMessage(),
            ];
            return $response; // ← array, no JsonResponse
        }

        $cdr = $result->getCdrResponse();

        $response['cdrResponse'] = [
            'code' => $cdr->getCode(),
            'description' => $cdr->getDescription(),
            'notes' => $cdr->getNotes(),
            'cdrZip' => base64_encode($result->getCdrZip()),
        ];

        return $response;
    }


    public function getHtmlReport($invoice)
    {
        $report = new HtmlReport();

        $resolver = new DefaultTemplateResolver();
        $report->setTemplate($resolver->getTemplate($invoice));


        $ruc = $invoice->getCompany()->getRuc();
        $company = Company::where('ruc', $ruc)->where('user_id', auth()->id())->first();


        $params = [
            'system' => [
                'logo' => $company->logo_path, // Logo de Empresa
                'hash' => 'qqnr2dN4p/HmaEA/CJuVGo7dv5g=', // Valor Resumen
            ],
            'user' => [
                'header'     => 'Telf: <b>(01) 123375</b>', // Texto que se ubica debajo de la dirección de empresa
                'extras'     => [
                    // Leyendas adicionales
                    ['name' => 'CONDICION DE PAGO', 'value' => 'Efectivo'],
                    ['name' => 'VENDEDOR', 'value' => 'GITHUB SELLER'],
                ],
                'footer' => '<p>Nro Resolucion: <b>3232323</b></p>'
            ]
        ];

        return $report->render($invoice, $params);
    }

    public function getPdfReport($invoice)
    {
        $html = $this->getHtmlReport($invoice);

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true);

        return $pdf->output();
    }

    public function savePdf($invoice, string $pdfContent): string
    {
        $ruc = $invoice->getCompany()->getRuc();
        $path = sprintf(
            'invoices/%s/%s-%s-%s.pdf',
            $ruc,
            $invoice->getTipoDoc(),
            $invoice->getSerie(),
            $invoice->getCorrelativo()
        );

        Storage::put($path, $pdfContent);

        return $path;
    }
}
