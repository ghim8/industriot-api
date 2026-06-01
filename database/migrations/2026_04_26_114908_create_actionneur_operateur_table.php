<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
   public function up(): void
{
    Schema::create('actionneur_operateur', function (Blueprint $table) {
        $table->id();
        $table->unsignedBigInteger('actionneur_id');
        $table->unsignedBigInteger('operateur_id');
        $table->unsignedBigInteger('affecte_par')->nullable();
        $table->timestamp('affecte_le')->nullable();
        $table->unique(['actionneur_id', 'operateur_id']);
    });
}

    public function down(): void
    {
        Schema::dropIfExists('actionneur_operateur');
    }
};