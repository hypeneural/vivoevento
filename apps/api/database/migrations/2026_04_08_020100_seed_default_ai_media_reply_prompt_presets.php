<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('ai_media_reply_prompt_presets')->upsert([
            [
                'slug' => 'casamento-romantico',
                'name' => 'Casamento romantico',
                'category' => 'casamento',
                'description' => 'Resposta delicada, afetiva e elegante para cenas de casamento.',
                'prompt_template' => 'Use um tom romantico, delicado e elegante. Gere uma resposta curta, calorosa e coerente com a cena. Use ate 2 emojis quando combinar com a imagem.',
                'sort_order' => 10,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'quinze-anos-brilho',
                'name' => '15 anos com brilho',
                'category' => '15anos',
                'description' => 'Resposta jovem e celebrativa para debutantes e cerimonias de 15 anos.',
                'prompt_template' => 'Use um tom alegre, jovem e celebrativo. Gere uma resposta curta, encantadora e coerente com a cena. Use ate 2 emojis quando fizer sentido.',
                'sort_order' => 20,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'corporativo-acolhedor',
                'name' => 'Corporativo acolhedor',
                'category' => 'corporativo',
                'description' => 'Resposta profissional, leve e acolhedora para eventos empresariais.',
                'prompt_template' => 'Use um tom profissional, leve e acolhedor. Gere uma resposta curta, natural e coerente com a imagem. Use emojis apenas quando combinarem com a cena.',
                'sort_order' => 30,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'festa-jovem-moderna',
                'name' => 'Festa jovem moderna',
                'category' => 'festas',
                'description' => 'Resposta descontraida para festas, baladas e comemoracoes animadas.',
                'prompt_template' => 'Use um tom jovem, moderno e descontraido. Gere uma resposta curta, vibrante e coerente com a cena. Use ate 2 emojis quando fizer sentido.',
                'sort_order' => 40,
                'is_active' => true,
                'created_by' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['slug'], ['name', 'category', 'description', 'prompt_template', 'sort_order', 'is_active', 'updated_at']);
    }

    public function down(): void
    {
        DB::table('ai_media_reply_prompt_presets')
            ->whereIn('slug', [
                'casamento-romantico',
                'quinze-anos-brilho',
                'corporativo-acolhedor',
                'festa-jovem-moderna',
            ])
            ->delete();
    }
};
