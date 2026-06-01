<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseController extends Controller
{
    protected function getEntrepriseId(Request $request): ?int
    {
        return $request->attributes->get('_entreprise_id') 
               ?? $request->user()?->entreprise_id;
    }
}