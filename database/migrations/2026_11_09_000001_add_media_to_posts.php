<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Posts desde archivo: el operador sube una foto o video y la IA
 * redacta el contenido. media_source distingue el flujo ('ai' es el
 * histórico, 'upload' el nuevo); las columnas source_* guardan el
 * archivo subido y media_description la salida del modelo de visión.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('media_source')->default('ai')->after('photo_keywords');
            $table->string('media_mode')->nullable()->after('media_source');
            $table->string('source_path')->nullable()->after('media_mode');
            $table->string('source_mime')->nullable()->after('source_path');
            $table->json('source_meta')->nullable()->after('source_mime');
            $table->json('media_description')->nullable()->after('source_meta');
            $table->text('operator_note')->nullable()->after('media_description');
            $table->timestamp('composing_at')->nullable()->after('operator_note');
            $table->text('compose_error')->nullable()->after('composing_at');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn([
                'media_source', 'media_mode', 'source_path', 'source_mime',
                'source_meta', 'media_description', 'operator_note',
                'composing_at', 'compose_error',
            ]);
        });
    }
};
