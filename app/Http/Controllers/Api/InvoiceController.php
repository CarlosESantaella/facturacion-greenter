<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use DateTime;
use Greenter\Model\Client\Client as GreenterClient;
use Greenter\Model\Company\Address as GreenterAddress;
use Greenter\Model\Company\Company as GreenterCompany;
use Greenter\Model\Sale\FormaPagos\FormaPagoContado as GreenterFormaPagoContado;
use Greenter\Model\Sale\Invoice as GreenterInvoice;
use Greenter\Model\Sale\Legend as GreenterLegend;
use Greenter\Model\Sale\SaleDetail as GreenterSaleDetail;
use Greenter\Report\XmlUtils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Luecano\NumeroALetras\NumeroALetras;

class InvoiceController extends Controller
{
    public function send(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', $request->user()->id)
            ->where('ruc', $data['company']['ruc'])
            ->firstOrFail();



        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $this->setTotales($data);

        $this->setLegends($data);

        $sunat = new \App\Services\SunatService();

        $see = $sunat->getSee($company);

        $invoice = $sunat->getInvoice($data);
        $result = $see->send($invoice);


        $response = $sunat->sunatResponse($result);
        $response['xml'] = $see->getFactory()->getLastXml();
        $response['hash'] = (new XmlUtils)->getHashSign($response['xml']);

        if ($response['success']) {
            $pdfContent = $sunat->getPdfReport($invoice);
            $response['pdf_path'] = $sunat->savePdf($invoice, $pdfContent);
        }

        return response()->json($response, 200);
    }

    public function setTotales(&$data)
    {
        $details = collect($data['detalle']) ?? collect([]);

        $data['mtoOperGravadas'] = $details->where('tipAfeIgv', 10)->sum('mtoValorVenta');
        $data['mtoOperExoneradas'] = $details->where('tipAfeIgv', 20)->sum('mtoValorVenta');
        $data['mtoOperInafectas'] = $details->where('tipAfeIgv', 30)->sum('mtoValorVenta');
        $data['mtoOperExportacion'] = $details->where('tipAfeIgv', 40)->sum('mtoValorVenta');
        $data['mtoOperGratuitas'] = $details->whereNotIn('tipAfeIgv', [10, 20, 30, 40])->sum('mtoValorVenta');

        $data['mtoIGV'] = $details->whereIn('tipAfeIgv', [10, 20, 30, 40])->sum('igv');
        $data['mtoIGVGratuitas'] = $details->whereNotIn('tipAfeIgv', [10, 20, 30, 40])->sum('igv');
        $data['icbper'] = $details->sum('icbper');
        $data['totalImpuestos'] = $data['mtoIGV'] + $data['mtoIGVGratuitas'] + $data['icbper'];

        $data['valorVenta'] = $details->whereIn('tipAfeIgv', [10, 20, 30, 40])->sum('mtoValorVenta');
        $data['subTotal'] = $data['valorVenta'] + $data['mtoIGV'];

        $data['mtoImpVenta'] = floor(($data['subTotal']) * 10) / 10;

        $data['redondeo'] = $data['mtoImpVenta'] - $data['subTotal'];
    }

    public function setLegends(&$data)
    {
        $formatter = new NumeroALetras();

        $data['leyendas'] = [
            [
                'codigo' => '1000',
                'valor' => $formatter->toInvoice($data['mtoImpVenta'], 2, 'SOLES'),
            ]
        ];
    }

    public function xml(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', $request->user()->id)
            ->where('ruc', $data['company']['ruc'])
            ->firstOrFail();

        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $this->setTotales($data);

        $this->setLegends($data);

        $sunat = new \App\Services\SunatService();

        $see = $sunat->getSee($company);


        $invoice = $sunat->getInvoice($data);

        $response['xml'] = $see->getXmlSigned($invoice);
        $response['hash'] = (new XmlUtils)->getHashSign($response['xml']);

        return response()->json($response, 200);
    }

    public function pdf(Request $request)
    {
        $data = $request->all();

        $company = Company::where('user_id', $request->user()->id)
            ->where('ruc', $data['company']['ruc'])
            ->firstOrFail();

        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        $this->setTotales($data);

        $this->setLegends($data);

        $sunat = new \App\Services\SunatService();

        $invoice = $sunat->getInvoice($data);
        $pdfContent = $sunat->getPdfReport($invoice);
        $sunat->savePdf($invoice, $pdfContent);

        $filename = sprintf(
            '%s-%s-%s-%s.pdf',
            $data['company']['ruc'],
            $data['tipoDoc'],
            $data['serie'],
            $data['correlativo']
        );

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}
