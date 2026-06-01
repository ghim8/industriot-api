<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\AlerteController;
use App\Http\Controllers\Api\RelaiController;
use App\Http\Controllers\Api\UtilisateurController;
use App\Http\Controllers\Api\ConnexionController;
use App\Http\Controllers\Api\CapteurController;
use App\Http\Controllers\Api\AffectationController;
use App\Http\Controllers\Api\MesureController;
use App\Http\Controllers\Api\EntrepriseController;
use App\Http\Controllers\Api\ActionneurController;

// ── Routes publiques ──────────────────────────────────────────
Route::post('/login',       [AuthController::class, 'login']);
Route::post('/changer-mdp', [AuthController::class, 'changerMdp']);
Route::post('/logout',      [AuthController::class, 'logout']);

// ── Super Admin (auth uniquement, pas de tenant) ──────────────
Route::middleware('auth:sanctum')->prefix('super-admin')->group(function () {
    Route::get('/entreprises',              [EntrepriseController::class, 'index']);
    Route::post('/entreprises',             [EntrepriseController::class, 'store']);
    Route::delete('/entreprises/{id}',      [EntrepriseController::class, 'destroy']);
    Route::post('/entreprises/{id}/toggle', [EntrepriseController::class, 'toggleActif']);
    Route::get('/entreprises/{id}/stats',   [EntrepriseController::class, 'stats']);
});

// ── Routes protégées + filtre tenant ─────────────────────────
Route::middleware(['auth:sanctum', 'tenant'])->group(function () {

    Route::get('/me', [AuthController::class, 'me']);
    // Machines
    Route::get('/machines',                          [MachineController::class, 'index']);
    Route::get('/machines/{id}',                     [MachineController::class, 'show']);
    Route::post('/machines',                         [MachineController::class, 'store']);
    Route::put('/machines/{id}',                     [MachineController::class, 'update']);
    Route::delete('/machines/{id}',                  [MachineController::class, 'destroy']);
    Route::post('/machines/{id}/affecter',           [MachineController::class, 'affecter']);
    Route::post('/machines/{id}/retirer-affectation',[MachineController::class, 'retirerAffectation']);
    Route::get('/machines/{id}/affectations',        [MachineController::class, 'affectations']);
    Route::get('/machines/{topic}/config', [MachineController::class, 'getConfig']);
    
    // Actionneurs
    Route::get('/actionneurs',                           [ActionneurController::class, 'index']);
    Route::get('/actionneurs/par-operateur',             [ActionneurController::class, 'parOperateur']);
    Route::post('/actionneurs',                          [ActionneurController::class, 'store']);
    Route::put('/actionneurs/{id}',                      [ActionneurController::class, 'update']);
    Route::delete('/actionneurs/{id}',                   [ActionneurController::class, 'destroy']);
    Route::post('/actionneurs/{id}/affecter',            [ActionneurController::class, 'affecter']);
    Route::post('/actionneurs/{id}/retirer-affectation', [ActionneurController::class, 'retirerAffectation']);

    // Capteurs
    Route::get('/capteurs',         [CapteurController::class, 'index']);
    Route::post('/capteurs',        [CapteurController::class, 'store']);
    Route::put('/capteurs/{id}',    [CapteurController::class, 'update']);
    Route::delete('/capteurs/{id}', [CapteurController::class, 'destroy']);

    // Mesures
    Route::get('/mesures',  [MesureController::class, 'index']);
    Route::post('/mesures', [MesureController::class, 'store']);

    // Alertes
    Route::get('/alertes',                      [AlerteController::class, 'index']);
    Route::post('/alertes/{id}/acquitter',       [AlerteController::class, 'acquitter']);
    Route::delete('/alertes/{id}',               [AlerteController::class, 'destroy']);
    Route::post('/alertes/supprimer-selection',  [AlerteController::class, 'destroySelection']);

    // Relais
    Route::get('/relais/journal', [RelaiController::class, 'journal']);
    Route::get('/relais',      [RelaiController::class, 'index']);
    Route::put('/relais/{id}', [RelaiController::class, 'update']);
    Route::post('/journal-relais/supprimer-selection', [RelaiController::class, 'supprimerJournal']);
    // Utilisateurs
    Route::get('/utilisateurs',         [UtilisateurController::class, 'index']);
    Route::post('/utilisateurs',        [UtilisateurController::class, 'store']);
    Route::put('/utilisateurs/{id}',    [UtilisateurController::class, 'update']);
    Route::delete('/utilisateurs/{id}', [UtilisateurController::class, 'destroy']);
    Route::get('/operateurs',           [UtilisateurController::class, 'operateurs']);

    // Connexions
    Route::get('/connexions',                        [ConnexionController::class, 'index']);
    Route::delete('/connexions/{id}',                [ConnexionController::class, 'destroy']);
    Route::post('/connexions/supprimer-selection',   [ConnexionController::class, 'destroySelection']);

    // Affectations / Chefs
    Route::get('/chefs',          [AffectationController::class, 'index']);
    Route::post('/chefs',         [AffectationController::class, 'addChef']);
    Route::get('/mes-operateurs', [AffectationController::class, 'mesOperateurs']);
    Route::post('/affectations',  [AffectationController::class, 'affecter']);
    Route::delete('/affectations/{utilisateur_id}/{machine_id}', [AffectationController::class, 'retirer']);
    Route::get('/check-email', function(\Illuminate\Http\Request $request) {
    $email     = $request->query('email');
    $available = !\App\Models\Utilisateur::where('email', $email)->exists();
    return response()->json(['available' => $available]);
});
});