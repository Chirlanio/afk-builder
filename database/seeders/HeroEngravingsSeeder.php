<?php

namespace Database\Seeders;

use App\Models\Hero;
use App\Models\HeroEngraving;
use Illuminate\Database\Seeder;

/**
 * Eternal Engravings (E30 e E60) por herói curados do afk.guide.
 * Captura humana via extensão Claude Chrome — autorizada pelo usuário.
 *
 * Formato: ['slug' => ['e30' => ['label' => '...', 'description' => '...'], 'e60' => [...]]].
 * Heróis sem slug correspondente no DB são apenas pulados (log).
 */
class HeroEngravingsSeeder extends Seeder
{
    public function run(): void
    {
        $sourceUrl = 'https://afk.guide/eternal-engravings/';
        $entries = $this->engravings();

        $created = 0;
        $skipped = 0;
        $missing = [];

        foreach ($entries as $slug => $levels) {
            $hero = Hero::where('slug', $slug)->first();
            if (! $hero) {
                $missing[] = $slug;
                continue;
            }
            foreach ($levels as $level => $data) {
                HeroEngraving::updateOrCreate(
                    ['hero_id' => $hero->id, 'level' => $level, 'source' => 'afk.guide'],
                    [
                        'label' => $data['label'],
                        'description' => $data['description'],
                        'source_url' => $sourceUrl,
                    ],
                );
                $created++;
            }
        }

        if ($missing !== []) {
            $this->command?->warn('Heróis sem cadastro no DB ('.count($missing).'): '.implode(', ', array_slice($missing, 0, 20)));
        }
        $this->command?->info("Engravings persistidos: {$created}");
    }

    /**
     * @return array<string, array{e30: array{label:string, description:string}, e60: array{label:string, description:string}}>
     */
    private function engravings(): array
    {
        return [
            // ============== Celestials ==============
            'athalia' => [
                'e30' => ['label' => 'Purging Frenzy', 'description' => 'Dano final aumenta para 180%.'],
                'e60' => ['label' => 'Protection', 'description' => 'Bônus de stat multiplicado por 1.5x nos primeiros 8s.'],
            ],
            'elijah-lailah' => [
                'e30' => ['label' => 'Cleanse', 'description' => 'Escudo de Elijah 380%; knock-back 200%.'],
                'e60' => ['label' => 'Unity', 'description' => 'Aliado morto transfere 40% da energia restante ao sobrevivente.'],
            ],
            'orthros' => [
                'e30' => ['label' => 'Inertia', 'description' => 'Dano aumenta para 250%.'],
                'e60' => ['label' => 'Celestial Vigor', 'description' => 'HP máx +3%/s até 150%.'],
            ],
            'talene' => [
                'e30' => ['label' => 'Phoenix Rising', 'description' => 'Dano 320%.'],
                'e60' => ['label' => 'Afterglow', 'description' => 'Cura até 270% do Attack Rating.'],
            ],
            'wu-kong' => [
                'e30' => ['label' => 'Falling Fury', 'description' => 'Dano 350%.'],
                'e60' => ['label' => 'Foresight', 'description' => 'Haste +20 por 5s ao ativar.'],
            ],
            'flora' => [
                'e30' => ['label' => 'Swoop', 'description' => 'Passarinho gera escudo 75% do HP sacrificado para aliados.'],
                'e60' => ['label' => 'Rejuvenation', 'description' => 'Recupera 1.2% HP máx/s.'],
            ],
            'zaphrael' => [
                'e30' => ['label' => 'Lightning Arc', 'description' => 'Dano 260%.'],
                'e60' => ['label' => 'Static Charge', 'description' => 'Transferência elétrica ao exceder 8% do HP máximo do inimigo.'],
            ],
            'alna' => [
                'e30' => ['label' => "Winter's Call", 'description' => 'Inimigos próximos perdem 320 Dodge.'],
                'e60' => ['label' => 'Frozen Fury', 'description' => 'Dano 270%.'],
            ],
            'morael' => [
                'e30' => ['label' => 'Cosmic Fusion', 'description' => 'Dano 290%.'],
                'e60' => ['label' => 'Celestial Blessing', 'description' => 'Star Core transfere 180 de energia para Morael e aliados.'],
            ],
            'titus' => [
                'e30' => ['label' => 'Spiral', 'description' => 'Dano 230%.'],
                'e60' => ['label' => "Guardian's Shield", 'description' => 'Escudo até 1900% por 8s.'],
            ],

            // ============== Hypogeans ==============
            'ezizh' => [
                'e30' => ['label' => 'Horrify', 'description' => 'Dano 190%.'],
                'e60' => ['label' => 'Mental Fury', 'description' => 'Aliados ganham +100 energia ao matar inimigos.'],
            ],
            'mehira' => [
                'e30' => ['label' => 'Infatuation', 'description' => 'Suga 4% do HP atual do inimigo.'],
                'e60' => ['label' => 'Hellspawn', 'description' => 'HP sacrificado reduz para 30%.'],
            ],
            'zolrath' => [
                'e30' => ['label' => 'Annihilate', 'description' => 'ATK +1.2%/s até 80%.'],
                'e60' => ['label' => 'Déjà vu', 'description' => 'Imunidade a controle por 12s após time reversal.'],
            ],
            'khazard' => [
                'e30' => ['label' => 'Frozen Beam', 'description' => 'Congelamento por 4s.'],
                'e60' => ['label' => 'Frost Serpent', 'description' => 'Ataca inimigo com hálito de gelo (180% dano, 2s freeze) enquanto Frozen Mist ativo.'],
            ],
            'mezoth' => [
                'e30' => ['label' => 'Demonic Hunger', 'description' => 'Escudo = 16% HP máx.'],
                'e60' => ['label' => 'Carnivorous Lust', 'description' => 'HP máx +100%.'],
            ],
            'lucretia' => [
                'e30' => ['label' => 'Deathwish', 'description' => 'Dano recebido -20% nos primeiros 7s.'],
                'e60' => ['label' => 'Twin Terrors', 'description' => 'Dodge +100, Tenacity +35 na frontline.'],
            ],
            'mortas' => [
                'e30' => ['label' => 'Blood Pact', 'description' => 'Cura aliado mais ferido por 4.5x do HP consumido.'],
                'e60' => ['label' => 'Devilish Deal', 'description' => 'Inimigo perde 30 Haste.'],
            ],
            'leofric' => [
                'e30' => ['label' => 'Unmasked Horror', 'description' => 'Inimigos aterrorizados perdem 45% ATK.'],
                'e60' => ['label' => 'Perfect Disguise', 'description' => 'Crit Rating dos aliados +20%.'],
            ],
            'zikis' => [
                'e30' => ['label' => 'Languid Eye', 'description' => 'Dano extra = 20% HP máx do inimigo em Lethargy.'],
                'e60' => ['label' => 'Hatred', 'description' => 'Accuracy inimiga -160.'],
            ],

            // ============== Lightbearers ==============
            'belinda' => [
                'e30' => ['label' => 'Brilliance', 'description' => 'Crit Damage Amplification +18.'],
                'e60' => ['label' => 'Blessing', 'description' => '+15% dano por ataque (máx 4 stacks).'],
            ],
            'rowan' => [
                'e30' => ['label' => 'Healthy Supplies', 'description' => 'Poção a cada 4s.'],
                'e60' => ['label' => 'Damage Control', 'description' => 'Energia perdida reduzida para 35.'],
            ],
            'gwyneth' => [
                'e30' => ['label' => 'Lightning Arrow', 'description' => 'Stun +1s.'],
                'e60' => ['label' => 'Strength in Numbers', 'description' => 'Ataques de fogo/raio = 260% dano.'],
            ],
            'rosaline' => [
                'e30' => ['label' => 'Afternoon Tea', 'description' => 'Cura até 190% ATK.'],
                'e60' => ['label' => 'Spring Cleaning', 'description' => 'Inimigos próximos perdem 120 Accuracy.'],
            ],
            'thane' => [
                'e30' => ['label' => 'Execution', 'description' => '+21% HP máx do alvo como dano.'],
                'e60' => ['label' => 'Focus', 'description' => 'Cada Critical Strike +8% ATK por 5s (máx 10x).'],
            ],
            'hendrik' => [
                'e30' => ['label' => 'Sacrificial Shield', 'description' => 'Dano ao proteger aliado -70%.'],
                'e60' => ['label' => 'Standfast', 'description' => 'Recupera 8% HP/s + imunidade por 3s ao entrar em postura defensiva.'],
            ],
            'raine' => [
                'e30' => ['label' => 'Lock On', 'description' => 'HP recuperado = 60% ATK.'],
                'e60' => ['label' => 'Exploit', 'description' => 'Dano aliado +35%.'],
            ],
            'lucius' => [
                'e30' => ['label' => 'Divine Blessing', 'description' => 'Cura até 240% ATK.'],
                'e60' => ['label' => 'Blessed Shield', 'description' => 'Recupera 35% HP perdido ao usar escudo.'],
            ],
            'fawkes' => [
                'e30' => ['label' => 'Confine', 'description' => 'Caixão imediato ao chegar na posição.'],
                'e60' => ['label' => 'Silver Bolt', 'description' => '+40% dano a inimigos debuffados.'],
            ],
            'estrilda' => [
                'e30' => ['label' => 'Inspire', 'description' => 'ATK +40%.'],
                'e60' => ['label' => 'Ridicule', 'description' => 'ATK reduzida -18%.'],
            ],

            // ============== Maulers ==============
            'brutus' => [
                'e30' => ['label' => 'Brutal Defiance', 'description' => 'ATK +120%, Haste +28%.'],
                'e60' => ['label' => 'Last Gasp', 'description' => 'Ao ativar, recupera 800 energia.'],
            ],
            'safiya' => [
                'e30' => ['label' => 'Static Field', 'description' => 'Dano 100%/s.'],
                'e60' => ['label' => 'Spectral Disruption', 'description' => 'Após 25s, inimigos levam 200% dano + stun 1.5s.'],
            ],
            'kren' => [
                'e30' => ['label' => 'Old Pal', 'description' => '+100 energia por mina explodida.'],
                'e60' => ['label' => 'Concussive Blast', 'description' => 'Dano recebido de outros inimigos -50% durante habilidade.'],
            ],
            'skriath' => [
                'e30' => ['label' => 'Sandstorm', 'description' => 'Inimigos perdem 120 Accuracy e 35 Haste.'],
                'e60' => ['label' => 'Critical Insight', 'description' => 'ATK +10% ao atingir máximo de Crit Rating.'],
            ],
            'skreg' => [
                'e30' => ['label' => 'Ironskin', 'description' => 'Inimigos perto perdem 8 Haste/3s (máx 3x).'],
                'e60' => ['label' => 'Stampede', 'description' => 'Ativa a cada 6s.'],
            ],
            'satrana' => [
                'e30' => ['label' => 'Fireseeds', 'description' => 'Dano = 1.5% HP máx/s.'],
                'e60' => ['label' => 'Overflow', 'description' => 'Inimigos em chamas perdem 20 de Energy Recovery.'],
            ],
            'tidus' => [
                'e30' => ['label' => 'Battle Howl', 'description' => 'Efeitos duram 13s.'],
                'e60' => ['label' => 'Savagery', 'description' => 'Ao matar alvo, recupera 40% HP perdido + Haste +30 por 6s.'],
            ],
            'antandra' => [
                'e30' => ['label' => 'Piercing Assault', 'description' => 'Dano 260%.'],
                'e60' => ['label' => 'Knockdown', 'description' => 'Recupera 4% HP máx por inimigo marcado atingido.'],
            ],
            'granit' => [
                'e30' => ['label' => 'Stone Skin', 'description' => 'Escudo = 360% ATK.'],
                'e60' => ['label' => 'Earthen Resilience', 'description' => 'Escudo reseta a cada 8s no mínimo.'],
            ],
            'khasos' => [
                'e30' => ['label' => 'Throwing Axe', 'description' => 'Dano 170%.'],
                'e60' => ['label' => 'Rabid Thirst', 'description' => 'Life-leech de todos aliados +21.'],
            ],
            'numisu' => [
                'e30' => ['label' => 'Offensive Totem', 'description' => 'Mock dura 3s.'],
                'e60' => ['label' => 'Fanaticism', 'description' => 'Haste 60, velocidade de ataque normal +40%.'],
            ],
            'warek' => [
                'e30' => ['label' => 'Extricate', 'description' => 'Cooldown reduzido para 4s.'],
                'e60' => ['label' => 'Carnal Rage', 'description' => 'Dano +5%.'],
            ],
            'vurk' => [
                'e30' => ['label' => 'Poison Vial', 'description' => 'Inimigos envenenados perdem HP = 80% ATK/s.'],
                'e60' => ['label' => 'Poison Traps', 'description' => 'Após 20s coloca armadilhas no centro e atrás.'],
            ],
            'thali' => [
                'e30' => ['label' => 'Flame Burst', 'description' => 'Fireball 3 sempre Critical + 10% HP perdido.'],
                'e60' => ['label' => 'Pyromancy', 'description' => 'Após 10s sempre no estado poderoso.'],
            ],
            'anoki' => [
                'e30' => ['label' => 'Born To Lead', 'description' => 'Aliados melee Tenacity +15.'],
                'e60' => ['label' => 'Horn of War', 'description' => 'Escudo dura 7s.'],
            ],
            'drez' => [
                'e30' => ['label' => 'Tactical Retreat', 'description' => '+120 energia enquanto fora do campo.'],
                'e60' => ['label' => 'Rabid Slice', 'description' => 'ATK +3% por 5s ao usar ultimate (máx 10x).'],
            ],

            // ============== Wilders ==============
            'eironn' => [
                'e30' => ['label' => 'Vortex', 'description' => 'Dano 170%.'],
                'e60' => ['label' => 'Sylvan Oath', 'description' => '65% chance de ignorar defesa inimiga.'],
            ],
            'lyca' => [
                'e30' => ['label' => 'Awe', 'description' => 'Haste +10s duração.'],
                'e60' => ["label" => "Foe's Fragility", 'description' => 'Crit Block Rate -20%.'],
            ],
            'tasi' => [
                'e30' => ['label' => 'Banishment', 'description' => 'Rouba 55% ATK do alvo banido.'],
                'e60' => ['label' => 'Teleportation', 'description' => 'Dano 190%.'],
            ],
            'mishka' => [
                'e30' => ['label' => 'Call of the Wild', 'description' => 'Dano 500% contra escudo.'],
                'e60' => ['label' => 'Natural Rejuvenation', 'description' => 'Todos aliados curam e ganham escudo.'],
            ],
            'raku' => [
                'e30' => ['label' => 'Peckish', 'description' => 'Efeito dura 10s.'],
                'e60' => ["label" => "Raku's Mark", 'description' => 'Ataca inimigos sob CC com ataque normal por 6s.'],
            ],
            'lorsan' => [
                'e30' => ['label' => 'Gale Force', 'description' => 'Inimigo linkado stunnado 7s ao outro morrer.'],
                'e60' => ['label' => 'Wind Ward', 'description' => 'Escudo 350% ATK.'],
            ],
            'gorvo' => [
                'e30' => ['label' => 'Shell Shock', 'description' => 'Dano recebido -10% por 8s ao carregar martelo (máx 3x).'],
                'e60' => ['label' => 'Shell Shield', 'description' => 'Escudo = 60% HP máx.'],
            ],
            'saurus' => [
                'e30' => ['label' => 'Burst Strikes', 'description' => 'Dano 120%.'],
                'e60' => ['label' => 'Tenacity', 'description' => 'ATK +12%, DEF +30%.'],
            ],
            'pippa' => [
                'e30' => ['label' => 'Transmogrification', 'description' => 'Chance de falha reduzida para 10%.'],
                'e60' => ['label' => 'Astral Shift', 'description' => 'Inimigos na área ficam stunados 5s.'],
            ],
            'nemora' => [
                'e30' => ['label' => 'Mother Nature', 'description' => 'Regeneração de HP +30%.'],
                'e60' => ['label' => 'Life Force', 'description' => 'Aliado também recupera 100 energia imediatamente.'],
            ],
            'kaz' => [
                'e30' => ['label' => 'Evasive Strike', 'description' => 'Ativa a cada 6s.'],
                'e60' => ['label' => 'Feline Finesse', 'description' => '+10 Dodge ao desviar (máx 5x).'],
            ],
            'solise' => [
                'e30' => ['label' => 'Floral Specter', 'description' => 'Dano 110% ATK por passagem.'],
                'e60' => ["label" => "Forest's Blessing", 'description' => '2 aliados mais feridos recebem bênção.'],
            ],
            'respen' => [
                'e30' => ['label' => 'Winged Attack', 'description' => 'Dano 290%.'],
                'e60' => ['label' => 'Winged Protection', 'description' => 'Escudo dura 8s.'],
            ],
            'seirus' => [
                'e30' => ["label" => "Ocean's Blessing", 'description' => 'Ataque normal até 240%.'],
                'e60' => ['label' => 'Free Flow', 'description' => 'Dano -65%.'],
            ],
            'ulmus' => [
                'e30' => ["label" => "Forest's Oath", 'description' => 'Escudo = 240% ATK.'],
                'e60' => ['label' => 'Life Torrent', 'description' => '+6% HP perdido/s.'],
            ],

            // ============== Graveborns ==============
            'thoran' => [
                'e30' => ['label' => 'Resurrection', 'description' => 'ATK +20% por 10s após reviver.'],
                'e60' => ['label' => 'Taint', 'description' => 'HP Recovery do alvo amaldiçoado -50%.'],
            ],
            'daimon' => [
                'e30' => ['label' => 'Inanimate Companion', 'description' => 'Dano de Stitchy 220%.'],
                'e60' => ['label' => 'Forbidden Power', 'description' => 'Dano extra = 10% do escudo (máx 300% ATK).'],
            ],
            'grezhul' => [
                'e30' => ['label' => 'Shadow Shield', 'description' => 'Escudo = 240% ATK.'],
                'e60' => ['label' => 'Deathly Protection', 'description' => 'Debuffs de inimigos de inteligência -20% de duração.'],
            ],
            'oden' => [
                'e30' => ['label' => 'Eviction', 'description' => 'Dano 240%.'],
                'e60' => ['label' => 'Eye of Evil', 'description' => 'ATK +18%, Haste +20 por olho aberto.'],
            ],
            'ferael' => [
                'e30' => ['label' => 'Terrorize', 'description' => 'Inimigos stunados 4s quando < 3 aterrorizados.'],
                'e60' => ['label' => 'Corrupted Spirit', 'description' => 'Espíritos ficam 7s no campo.'],
            ],
            'kelthur' => [
                'e30' => ['label' => 'Displace', 'description' => 'Dano recebido +50% por 13s.'],
                'e60' => ['label' => 'Ethereal Resurgence', 'description' => 'ATK +25% até matar o inimigo que o matou.'],
            ],
            'silas' => [
                'e30' => ['label' => 'Double Dosage', 'description' => 'Aliados recuperam 40% ATK/s como HP.'],
                'e60' => ['label' => 'Healing Haze', 'description' => 'Aliados na névoa recebem 10% menos dano.'],
            ],
            'baden' => [
                'e30' => ['label' => 'Spectral Onslaught', 'description' => 'Dano 160%.'],
                'e60' => ['label' => 'Spectral Surge', 'description' => 'Se >4 fantasmas no campo, Baden fica untargetable 4s (a cada 15s).'],
            ],
            'nara' => [
                'e30' => ['label' => 'Impale', 'description' => 'Inimigo sangra 70% ATK/s por 8s.'],
                'e60' => ['label' => 'Terrify', 'description' => 'Alvo pode receber o efeito 5x em 10s.'],
            ],
            'shemira' => [
                'e30' => ['label' => 'Silence', 'description' => 'Dano 170%.'],
                'e60' => ['label' => 'Wrath', 'description' => 'Após Tortured Souls, ignora requisito de HP por 8s.'],
            ],
            'isabella' => [
                'e30' => ['label' => 'Psi Reap', 'description' => 'Rouba 30 Crit Rating permanentemente se terminar o spell.'],
                'e60' => ['label' => 'Surge', 'description' => 'Velocidade de cast +5% por spell bem-sucedido (máx 5x).'],
            ],
            'izold' => [
                'e30' => ['label' => 'Needle Strike', 'description' => 'Dano final 380%.'],
                'e60' => ['label' => 'Hypodermic Healing', 'description' => 'Efeito dura 10s.'],
            ],
            'desira' => [
                'e30' => ['label' => 'Oceanic Shield', 'description' => 'Aliado mais ferido recupera 28% HP máx de Desira.'],
                'e60' => ['label' => 'Oceanic Mist', 'description' => 'Cura = 22% ATK de Desira.'],
            ],
            'torne' => [
                'e30' => ['label' => 'Seething Death', 'description' => 'Dano 210%.'],
                'e60' => ['label' => 'Absorption', 'description' => 'Recupera 50% HP máx.'],
            ],
            'theowyn' => [
                'e30' => ['label' => 'Spectral Curse', 'description' => 'Dano 200%.'],
                'e60' => ['label' => 'Song of Mourning', 'description' => 'Ativa ao receber qualquer dano > 8% HP.'],
            ],
            'hodgkin' => [
                'e30' => ['label' => 'Jolly Roger', 'description' => 'Dano 280%.'],
                'e60' => ["label" => "Reviler's Defiance", 'description' => 'Sempre causa 20% HP máx inimigo.'],
            ],

            // ============== Dimensionals ==============
            'ainz-ooal-gown' => [
                'e30' => ['label' => 'The Goal', 'description' => 'Inimigos perdem 55% HP atual.'],
                'e60' => ['label' => 'Steady Preparations', 'description' => 'ATK +7%/s, DEF +12%/s.'],
            ],
            'arthur' => [
                'e30' => ["label" => "King's Blessing", 'description' => 'Dano 240%.'],
                'e60' => ['label' => 'Shield of Honor', 'description' => 'Critical Strikes de frente -30% dano + retorna ao atacante.'],
            ],
            'merlin' => [
                'e30' => ['label' => 'Karmic Law', 'description' => 'Inimigo perde HP = 180% ATK/s.'],
                'e60' => ['label' => 'Guardian Owl', 'description' => 'Dano recebido pelo aliado -40% enquanto em forma de espírito.'],
            ],
            'queen' => [
                'e30' => ['label' => 'Attack & Defend', 'description' => 'Crit +10%, DEF +35% por aumento de atributo.'],
                'e60' => ['label' => 'Ms. Post-Apocalyptic Raider', 'description' => 'Dano 220%.'],
            ],
            'joker' => [
                'e30' => ['label' => 'Concentrated Fire', 'description' => '220% dano ao alvo.'],
                'e60' => ['label' => 'All-Out Attack', 'description' => '360% dano a todos inimigos.'],
            ],
            'leonardo' => [
                'e30' => ['label' => 'Renaissance Cannon', 'description' => 'Usar Evacuation reseta cooldown.'],
                'e60' => ['label' => 'Evacuation', 'description' => 'Após usar, -50% dano recebido por 5s.'],
            ],
            'nakoruru' => [
                'e30' => ['label' => 'Shichikapu Etu', 'description' => 'Dano 240%.'],
                'e60' => ['label' => 'Kamui Mutsube', 'description' => 'Dano 330%.'],
            ],
            'ukyo' => [
                'e30' => ['label' => 'Phantom Strike', 'description' => 'Dano 210%.'],
                'e60' => ['label' => 'Swallow Swipe', 'description' => 'Dano 320%.'],
            ],
            'ezio' => [
                'e30' => ['label' => 'Smoke Bomb', 'description' => 'Inimigos perdem 145 Accuracy.'],
                'e60' => ['label' => 'Evasion', 'description' => '+6 Dodge/s até 150.'],
            ],
            'albedo' => [
                'e30' => ['label' => 'Shield Lord', 'description' => 'Dano 300%.'],
                'e60' => ['label' => 'Hermes Trismegistus', 'description' => 'Atacante de Critical Strike perde 70% ATK.'],
            ],
            'prince-of-persia' => [
                'e30' => ['label' => 'Wall Dive Attack', 'description' => '18% HP máx inimigo (máx 380% ATK).'],
                'e60' => ['label' => 'Concentrated Force', 'description' => 'Recupera até 65 energia.'],
            ],
        ];
    }
}
