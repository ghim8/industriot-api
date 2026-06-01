<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Créer la table mesures si elle n'existe pas
        if (!Schema::hasTable('mesures')) {
            Schema::create('mesures', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('machine_id')->nullable();
                $table->unsignedBigInteger('capteur_id')->nullable();
                $table->unsignedBigInteger('actionneur_id')->nullable();
                $table->decimal('valeur', 12, 4);
                $table->tinyInteger('hors_seuil')->default(0);
                $table->timestamp('horodatage')->useCurrent();
            });
        } else {
            // Ajouter la colonne si elle n'existe pas
            if (!Schema::hasColumn('mesures', 'actionneur_id')) {
                Schema::table('mesures', function (Blueprint $table) {
                    $table->unsignedBigInteger('actionneur_id')->nullable()->after('capteur_id');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mesures', 'actionneur_id')) {
            Schema::table('mesures', function (Blueprint $table) {
                $table->dropColumn('actionneur_id');
            });
        }
    }
};