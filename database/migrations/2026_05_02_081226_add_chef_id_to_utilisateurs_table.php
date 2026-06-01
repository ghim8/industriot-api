<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Créer la table utilisateurs si elle n'existe pas
        if (!Schema::hasTable('utilisateurs')) {
            Schema::create('utilisateurs', function (Blueprint $table) {
                $table->id();
                $table->string('nom', 100);
                $table->string('email', 150)->unique();
                $table->string('mot_de_passe', 255);
                $table->enum('role', ['super_admin','admin','chef','operateur'])->default('operateur');
                $table->string('initiales', 4)->default('');
                $table->enum('statut', ['ACTIF','INACTIF'])->default('ACTIF');
                $table->unsignedInteger('chef_id')->nullable();
                $table->tinyInteger('mdp_change')->default(0);
                $table->timestamps();
            });
        } else {
            if (!Schema::hasColumn('utilisateurs', 'chef_id')) {
                Schema::table('utilisateurs', function (Blueprint $table) {
                    $table->unsignedInteger('chef_id')->nullable()->after('statut');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('utilisateurs', 'chef_id')) {
            Schema::table('utilisateurs', function (Blueprint $table) {
                $table->dropColumn('chef_id');
            });
        }
    }
};