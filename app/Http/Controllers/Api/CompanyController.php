<?php

namespace App\Http\Controllers\Api;

use App\Models\Company;
use App\Rules\UniqueRucRule;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CompanyController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json(Company::where('user_id', Auth::user()->id)->get(), 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $data = $request->validate([
            'razon_social' => 'required|string|max:255',
            'ruc' => [
                'required',
                'string',
                'size:11',
                // 'unique:companies,ruc',
                'regex:/^(10|20)\d{9}$/',
                new UniqueRucRule(),
            ],
            'direccion' => 'nullable|string|max:255',
            'logo' => 'nullable|image',
            'logo_path' => 'nullable|string|max:255',
            'sol_user' => 'nullable|string|max:255',
            'sol_pass' => 'nullable|string|max:255',
            'cert' => 'required|file|mimes:pem,txt',
            'cert_path' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'production' => 'nullable|boolean',
        ]);


        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos');
        }

        if ($request->hasFile('cert')) {
            $data['cert_path'] = $request->file('cert')->store('certs');
        }

        $company = Company::create([
            'razon_social' => $data['razon_social'],
            'ruc' => $data['ruc'],
            'direccion' => $data['direccion'] ?? null,
            'logo_path' => $data['logo_path'] ?? null,
            'sol_user' => $data['sol_user'] ?? null,
            'sol_pass' => $data['sol_pass'] ?? null,
            'cert_path' => $data['cert_path'] ?? null,
            'client_id' => $data['client_id'] ?? null,
            'client_secret' => $data['client_secret'] ?? null,
            'production' => $data['production'] ?? false,
            'user_id' => $request->user()->id,
        ]);

        return response()->json(['message' => 'Company created successfully', 'company' => $company], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($companyRuc)
    {
        $company = Company::where('ruc', $companyRuc)->where('user_id', Auth::user()->id)->first();
        return response()->json($company, 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $companyRuc)
    {
        $company = Company::where('ruc', $companyRuc)->where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'razon_social' => 'nullable|string|max:255',
            'ruc' => [
                'nullable',
                'string',
                'size:11',
                // 'unique:companies,ruc',
                'regex:/^(10|20)\d{9}$/',
                new UniqueRucRule($company->id),
            ],
            'direccion' => 'nullable|string|max:255',
            'logo' => 'nullable|image',
            'logo_path' => 'nullable|string|max:255',
            'sol_user' => 'nullable|string|max:255',
            'sol_pass' => 'nullable|string|max:255',
            'cert' => 'nullable|file|mimes:pem,txt',
            'cert_path' => 'nullable|string|max:255',
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:255',
            'production' => 'nullable|boolean',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos');
        }

        if ($request->hasFile('cert')) {
            $data['cert_path'] = $request->file('cert')->store('certs');
        }

        $company->update($data);

        return response()->json(['message' => 'Company created successfully', 'company' => $company->refresh()], 201);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($companyRuc)
    {
        $company = Company::where('ruc', $companyRuc)->where('user_id', Auth::user()->id)->firstOrFail();
        $company->delete();
        return response()->json(['message' => 'Company deleted successfully'], 200);
    }
}
