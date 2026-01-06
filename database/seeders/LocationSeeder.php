<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $locations = [
            // ===== FEDERACIJA BiH - KANTON SARAJEVO =====
            ['name' => 'Sarajevo', 'postal_code' => '71000', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.8563, 'longitude' => 18.4131, 'population' => 275524],
            ['name' => 'Ilidža', 'postal_code' => '71210', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.8297, 'longitude' => 18.3103, 'population' => 71892],
            ['name' => 'Vogošća', 'postal_code' => '71320', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.9019, 'longitude' => 18.3428, 'population' => 27800],
            ['name' => 'Hadžići', 'postal_code' => '71240', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.8222, 'longitude' => 18.2061, 'population' => 24920],
            ['name' => 'Ilijaš', 'postal_code' => '71380', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.9511, 'longitude' => 18.2728, 'population' => 19603],
            ['name' => 'Trnovo', 'postal_code' => '71220', 'entity' => 'FBiH', 'canton' => 'Kanton Sarajevo', 'latitude' => 43.6606, 'longitude' => 18.4478, 'population' => 1500],

            // ===== FEDERACIJA BiH - TUZLANSKI KANTON =====
            ['name' => 'Tuzla', 'postal_code' => '75000', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.5384, 'longitude' => 18.6763, 'population' => 110979],
            ['name' => 'Lukavac', 'postal_code' => '75300', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.5428, 'longitude' => 18.5297, 'population' => 44520],
            ['name' => 'Živinice', 'postal_code' => '75270', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.4494, 'longitude' => 18.6497, 'population' => 55635],
            ['name' => 'Gračanica', 'postal_code' => '75320', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.7033, 'longitude' => 18.3081, 'population' => 45220],
            ['name' => 'Gradačac', 'postal_code' => '76250', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.8781, 'longitude' => 18.4253, 'population' => 39340],
            ['name' => 'Srebrenik', 'postal_code' => '75350', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.7094, 'longitude' => 18.4903, 'population' => 41000],
            ['name' => 'Banovići', 'postal_code' => '75290', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.4089, 'longitude' => 18.5392, 'population' => 24275],
            ['name' => 'Kalesija', 'postal_code' => '75260', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.4500, 'longitude' => 18.8833, 'population' => 35000],
            ['name' => 'Kladanj', 'postal_code' => '75280', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.2272, 'longitude' => 18.6883, 'population' => 13300],
            ['name' => 'Čelić', 'postal_code' => '75246', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.7167, 'longitude' => 18.8167, 'population' => 12000],
            ['name' => 'Sapna', 'postal_code' => '75411', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.5000, 'longitude' => 19.0000, 'population' => 13000],
            ['name' => 'Teočak', 'postal_code' => '75414', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.5833, 'longitude' => 19.0333, 'population' => 8000],
            ['name' => 'Doboj-Istok', 'postal_code' => '74207', 'entity' => 'FBiH', 'canton' => 'Tuzlanski kanton', 'latitude' => 44.7500, 'longitude' => 18.1500, 'population' => 10000],

            // ===== FEDERACIJA BiH - ZENIČKO-DOBOJSKI KANTON =====
            ['name' => 'Zenica', 'postal_code' => '72000', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.2017, 'longitude' => 17.9078, 'population' => 115134],
            ['name' => 'Visoko', 'postal_code' => '71300', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 43.9889, 'longitude' => 18.1781, 'population' => 41352],
            ['name' => 'Kakanj', 'postal_code' => '72240', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.1333, 'longitude' => 18.1167, 'population' => 38937],
            ['name' => 'Tešanj', 'postal_code' => '74260', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.6122, 'longitude' => 17.9833, 'population' => 48175],
            ['name' => 'Maglaj', 'postal_code' => '74250', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.5483, 'longitude' => 18.0978, 'population' => 23366],
            ['name' => 'Zavidovići', 'postal_code' => '72220', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.4461, 'longitude' => 18.1500, 'population' => 36905],
            ['name' => 'Žepče', 'postal_code' => '72230', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.4267, 'longitude' => 18.0389, 'population' => 31400],
            ['name' => 'Breza', 'postal_code' => '71370', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.0206, 'longitude' => 18.2611, 'population' => 14564],
            ['name' => 'Olovo', 'postal_code' => '71340', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.1278, 'longitude' => 18.5806, 'population' => 10175],
            ['name' => 'Vareš', 'postal_code' => '71330', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.1622, 'longitude' => 18.3267, 'population' => 8892],
            ['name' => 'Usora', 'postal_code' => '74230', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.5500, 'longitude' => 17.9833, 'population' => 7000],
            ['name' => 'Doboj-Jug', 'postal_code' => '74203', 'entity' => 'FBiH', 'canton' => 'Zeničko-dobojski kanton', 'latitude' => 44.6833, 'longitude' => 18.0667, 'population' => 5000],

            // ===== FEDERACIJA BiH - UNSKO-SANSKI KANTON =====
            ['name' => 'Bihać', 'postal_code' => '77000', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.8169, 'longitude' => 15.8708, 'population' => 56261],
            ['name' => 'Cazin', 'postal_code' => '77220', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.9667, 'longitude' => 15.9431, 'population' => 66149],
            ['name' => 'Velika Kladuša', 'postal_code' => '77230', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 45.1842, 'longitude' => 15.8067, 'population' => 44974],
            ['name' => 'Sanski Most', 'postal_code' => '79260', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.7656, 'longitude' => 16.6656, 'population' => 47359],
            ['name' => 'Bosanska Krupa', 'postal_code' => '77240', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.8833, 'longitude' => 16.1500, 'population' => 28228],
            ['name' => 'Ključ', 'postal_code' => '79280', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.5333, 'longitude' => 16.7833, 'population' => 17001],
            ['name' => 'Bosanski Petrovac', 'postal_code' => '77250', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 44.5547, 'longitude' => 16.3700, 'population' => 7328],
            ['name' => 'Bužim', 'postal_code' => '77245', 'entity' => 'FBiH', 'canton' => 'Unsko-sanski kanton', 'latitude' => 45.0500, 'longitude' => 16.0333, 'population' => 20000],

            // ===== FEDERACIJA BiH - SREDNJOBOSANSKI KANTON =====
            ['name' => 'Travnik', 'postal_code' => '72270', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.2264, 'longitude' => 17.6656, 'population' => 53482],
            ['name' => 'Vitez', 'postal_code' => '72250', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.1519, 'longitude' => 17.7900, 'population' => 26814],
            ['name' => 'Bugojno', 'postal_code' => '70230', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.0572, 'longitude' => 17.4511, 'population' => 31470],
            ['name' => 'Novi Travnik', 'postal_code' => '72290', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.1667, 'longitude' => 17.6500, 'population' => 24138],
            ['name' => 'Jajce', 'postal_code' => '70101', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.3414, 'longitude' => 17.2692, 'population' => 27258],
            ['name' => 'Gornji Vakuf-Uskoplje', 'postal_code' => '70240', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 43.9381, 'longitude' => 17.5878, 'population' => 22304],
            ['name' => 'Busovača', 'postal_code' => '72260', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.0992, 'longitude' => 17.8872, 'population' => 18488],
            ['name' => 'Fojnica', 'postal_code' => '71270', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 43.9594, 'longitude' => 17.8942, 'population' => 12388],
            ['name' => 'Kiseljak', 'postal_code' => '71250', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 43.9400, 'longitude' => 18.0778, 'population' => 21919],
            ['name' => 'Kreševo', 'postal_code' => '71260', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 43.8792, 'longitude' => 18.0586, 'population' => 5264],
            ['name' => 'Donji Vakuf', 'postal_code' => '70220', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.1417, 'longitude' => 17.4003, 'population' => 14739],
            ['name' => 'Dobretići', 'postal_code' => '70102', 'entity' => 'FBiH', 'canton' => 'Srednjobosanski kanton', 'latitude' => 44.4167, 'longitude' => 17.2333, 'population' => 2500],

            // ===== FEDERACIJA BiH - HERCEGOVAČKO-NERETVANSKI KANTON =====
            ['name' => 'Mostar', 'postal_code' => '88000', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.3438, 'longitude' => 17.8078, 'population' => 105797],
            ['name' => 'Čapljina', 'postal_code' => '88300', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.1214, 'longitude' => 17.7050, 'population' => 26530],
            ['name' => 'Konjic', 'postal_code' => '88400', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.6517, 'longitude' => 17.9617, 'population' => 26381],
            ['name' => 'Jablanica', 'postal_code' => '88420', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.6600, 'longitude' => 17.7589, 'population' => 12141],
            ['name' => 'Čitluk', 'postal_code' => '88260', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.2278, 'longitude' => 17.7000, 'population' => 15883],
            ['name' => 'Stolac', 'postal_code' => '88360', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.0839, 'longitude' => 17.9594, 'population' => 14614],
            ['name' => 'Neum', 'postal_code' => '88390', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 42.9264, 'longitude' => 17.6156, 'population' => 4960],
            ['name' => 'Prozor-Rama', 'postal_code' => '88440', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 43.8222, 'longitude' => 17.6094, 'population' => 16568],
            ['name' => 'Ravno', 'postal_code' => '88370', 'entity' => 'FBiH', 'canton' => 'Hercegovačko-neretvanski kanton', 'latitude' => 42.8833, 'longitude' => 18.0000, 'population' => 3000],

            // ===== FEDERACIJA BiH - ZAPADNOHERCEGOVAČKI KANTON =====
            ['name' => 'Široki Brijeg', 'postal_code' => '88220', 'entity' => 'FBiH', 'canton' => 'Zapadnohercegovački kanton', 'latitude' => 43.3833, 'longitude' => 17.5939, 'population' => 28929],
            ['name' => 'Grude', 'postal_code' => '88340', 'entity' => 'FBiH', 'canton' => 'Zapadnohercegovački kanton', 'latitude' => 43.3756, 'longitude' => 17.4153, 'population' => 16178],
            ['name' => 'Ljubuški', 'postal_code' => '88320', 'entity' => 'FBiH', 'canton' => 'Zapadnohercegovački kanton', 'latitude' => 43.1967, 'longitude' => 17.5478, 'population' => 24138],
            ['name' => 'Posušje', 'postal_code' => '88240', 'entity' => 'FBiH', 'canton' => 'Zapadnohercegovački kanton', 'latitude' => 43.4722, 'longitude' => 17.3306, 'population' => 17603],

            // ===== FEDERACIJA BiH - BOSANSKO-PODRINJSKI KANTON =====
            ['name' => 'Goražde', 'postal_code' => '73000', 'entity' => 'FBiH', 'canton' => 'Bosansko-podrinjski kanton', 'latitude' => 43.6667, 'longitude' => 18.9764, 'population' => 22191],
            ['name' => 'Pale-Prača', 'postal_code' => '73290', 'entity' => 'FBiH', 'canton' => 'Bosansko-podrinjski kanton', 'latitude' => 43.7167, 'longitude' => 18.8500, 'population' => 5000],
            ['name' => 'Foča-Ustikolina', 'postal_code' => '73300', 'entity' => 'FBiH', 'canton' => 'Bosansko-podrinjski kanton', 'latitude' => 43.5500, 'longitude' => 18.7833, 'population' => 3000],

            // ===== FEDERACIJA BiH - KANTON 10 (LIVANJSKI) =====
            ['name' => 'Livno', 'postal_code' => '80101', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 43.8267, 'longitude' => 17.0075, 'population' => 26705],
            ['name' => 'Tomislavgrad', 'postal_code' => '80240', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 43.7189, 'longitude' => 17.2250, 'population' => 29284],
            ['name' => 'Kupres', 'postal_code' => '80320', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 43.9978, 'longitude' => 17.2867, 'population' => 5000],
            ['name' => 'Bosansko Grahovo', 'postal_code' => '80270', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 44.1833, 'longitude' => 16.6500, 'population' => 2000],
            ['name' => 'Glamoč', 'postal_code' => '80230', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 44.0489, 'longitude' => 16.8533, 'population' => 4038],
            ['name' => 'Drvar', 'postal_code' => '80260', 'entity' => 'FBiH', 'canton' => 'Kanton 10', 'latitude' => 44.3750, 'longitude' => 16.3853, 'population' => 7000],

            // ===== REPUBLIKA SRPSKA - BANJALUČKA REGIJA =====
            ['name' => 'Banja Luka', 'postal_code' => '78000', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.7722, 'longitude' => 17.1910, 'population' => 185042],
            ['name' => 'Prijedor', 'postal_code' => '79101', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.9797, 'longitude' => 16.7131, 'population' => 89397],
            ['name' => 'Gradiška', 'postal_code' => '78400', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 45.1456, 'longitude' => 17.2544, 'population' => 51727],
            ['name' => 'Laktaši', 'postal_code' => '78250', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.9083, 'longitude' => 17.3022, 'population' => 36000],
            ['name' => 'Prnjavor', 'postal_code' => '78430', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.8667, 'longitude' => 17.6667, 'population' => 36500],
            ['name' => 'Kotor Varoš', 'postal_code' => '78220', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.6167, 'longitude' => 17.3833, 'population' => 22000],
            ['name' => 'Mrkonjić Grad', 'postal_code' => '70260', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.4175, 'longitude' => 17.0858, 'population' => 18000],
            ['name' => 'Čelinac', 'postal_code' => '78240', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.7333, 'longitude' => 17.3167, 'population' => 17000],
            ['name' => 'Kneževo', 'postal_code' => '78230', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.4917, 'longitude' => 17.3833, 'population' => 10000],
            ['name' => 'Kozarska Dubica', 'postal_code' => '79240', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 45.1767, 'longitude' => 16.8092, 'population' => 22000],
            ['name' => 'Novi Grad', 'postal_code' => '79220', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 45.0469, 'longitude' => 16.3775, 'population' => 28000],
            ['name' => 'Srbac', 'postal_code' => '78420', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 45.0983, 'longitude' => 17.5239, 'population' => 19000],
            ['name' => 'Šipovo', 'postal_code' => '70270', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.2833, 'longitude' => 17.0833, 'population' => 11000],
            ['name' => 'Ribnik', 'postal_code' => '79288', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.4250, 'longitude' => 16.8250, 'population' => 5000],
            ['name' => 'Jezero', 'postal_code' => '70109', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.3167, 'longitude' => 17.1500, 'population' => 1000],
            ['name' => 'Oštra Luka', 'postal_code' => '79287', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.5167, 'longitude' => 16.9167, 'population' => 5000],
            ['name' => 'Petrovac', 'postal_code' => '70210', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.6333, 'longitude' => 16.9500, 'population' => 5000],
            ['name' => 'Krupa na Uni', 'postal_code' => '79252', 'entity' => 'RS', 'region' => 'Banjalučka regija', 'latitude' => 44.8833, 'longitude' => 16.3167, 'population' => 3000],

            // ===== REPUBLIKA SRPSKA - DOBOJSKA REGIJA =====
            ['name' => 'Doboj', 'postal_code' => '74000', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.7322, 'longitude' => 18.0872, 'population' => 72174],
            ['name' => 'Modriča', 'postal_code' => '74480', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.9544, 'longitude' => 18.3028, 'population' => 28000],
            ['name' => 'Derventa', 'postal_code' => '74400', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.9806, 'longitude' => 17.9083, 'population' => 27000],
            ['name' => 'Teslić', 'postal_code' => '74270', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.6058, 'longitude' => 17.8572, 'population' => 38700],
            ['name' => 'Šamac', 'postal_code' => '76230', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 45.0667, 'longitude' => 18.4667, 'population' => 16000],
            ['name' => 'Brod', 'postal_code' => '74450', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 45.1333, 'longitude' => 17.9833, 'population' => 18000],
            ['name' => 'Petrovo', 'postal_code' => '74317', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.6167, 'longitude' => 18.3500, 'population' => 6500],
            ['name' => 'Stanari', 'postal_code' => '74208', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.7333, 'longitude' => 17.8333, 'population' => 5000],
            ['name' => 'Vukosavlje', 'postal_code' => '74470', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.9833, 'longitude' => 18.1833, 'population' => 5000],
            ['name' => 'Donji Žabar', 'postal_code' => '76272', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 45.0167, 'longitude' => 18.6333, 'population' => 3000],
            ['name' => 'Pelagićevo', 'postal_code' => '76256', 'entity' => 'RS', 'region' => 'Dobojska regija', 'latitude' => 44.9167, 'longitude' => 18.5833, 'population' => 7000],

            // ===== REPUBLIKA SRPSKA - BIJELJINSKA REGIJA =====
            ['name' => 'Bijeljina', 'postal_code' => '76300', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.7567, 'longitude' => 19.2139, 'population' => 107715],
            ['name' => 'Zvornik', 'postal_code' => '75400', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.3858, 'longitude' => 19.1025, 'population' => 59024],
            ['name' => 'Ugljevik', 'postal_code' => '76330', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.6833, 'longitude' => 19.0167, 'population' => 16000],
            ['name' => 'Lopare', 'postal_code' => '75240', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.6333, 'longitude' => 18.8500, 'population' => 14000],
            ['name' => 'Bratunac', 'postal_code' => '75420', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.1839, 'longitude' => 19.3308, 'population' => 21000],
            ['name' => 'Srebrenica', 'postal_code' => '75430', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.1064, 'longitude' => 19.2967, 'population' => 13000],
            ['name' => 'Milići', 'postal_code' => '75446', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.1667, 'longitude' => 19.0833, 'population' => 12000],
            ['name' => 'Šekovići', 'postal_code' => '75450', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.2833, 'longitude' => 18.8500, 'population' => 8000],
            ['name' => 'Osmaci', 'postal_code' => '75406', 'entity' => 'RS', 'region' => 'Bijeljinska regija', 'latitude' => 44.3333, 'longitude' => 18.9667, 'population' => 6000],

            // ===== REPUBLIKA SRPSKA - SARAJEVSKO-ROMANIJSKA REGIJA =====
            ['name' => 'Istočno Sarajevo', 'postal_code' => '71123', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.8186, 'longitude' => 18.4450, 'population' => 61516],
            ['name' => 'Pale', 'postal_code' => '71420', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.8167, 'longitude' => 18.5667, 'population' => 22282],
            ['name' => 'Sokolac', 'postal_code' => '71350', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.9375, 'longitude' => 18.7953, 'population' => 12871],
            ['name' => 'Rogatica', 'postal_code' => '73220', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.7978, 'longitude' => 19.0011, 'population' => 11593],
            ['name' => 'Višegrad', 'postal_code' => '73240', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.7831, 'longitude' => 19.2875, 'population' => 11000],
            ['name' => 'Rudo', 'postal_code' => '73260', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.6167, 'longitude' => 19.3667, 'population' => 8000],
            ['name' => 'Han Pijesak', 'postal_code' => '71360', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 44.0833, 'longitude' => 18.9500, 'population' => 3000],
            ['name' => 'Čajniče', 'postal_code' => '73280', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.5572, 'longitude' => 19.0717, 'population' => 5000],
            ['name' => 'Novo Goražde', 'postal_code' => '73100', 'entity' => 'RS', 'region' => 'Sarajevsko-romanijska regija', 'latitude' => 43.6500, 'longitude' => 19.0167, 'population' => 4000],

            // ===== REPUBLIKA SRPSKA - TREBINJSKA REGIJA =====
            ['name' => 'Trebinje', 'postal_code' => '89101', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 42.7117, 'longitude' => 18.3436, 'population' => 31433],
            ['name' => 'Foča', 'postal_code' => '73300', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.5069, 'longitude' => 18.7756, 'population' => 19200],
            ['name' => 'Nevesinje', 'postal_code' => '88280', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.2583, 'longitude' => 18.1136, 'population' => 13000],
            ['name' => 'Gacko', 'postal_code' => '89240', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.1667, 'longitude' => 18.5333, 'population' => 9500],
            ['name' => 'Bileća', 'postal_code' => '89230', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 42.8764, 'longitude' => 18.4294, 'population' => 11000],
            ['name' => 'Kalinovik', 'postal_code' => '71230', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.5000, 'longitude' => 18.4500, 'population' => 4000],
            ['name' => 'Ljubinje', 'postal_code' => '88380', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 42.9500, 'longitude' => 18.0833, 'population' => 4000],
            ['name' => 'Berkovići', 'postal_code' => '88363', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.1000, 'longitude' => 18.2500, 'population' => 2000],
            ['name' => 'Istočni Mostar', 'postal_code' => '88201', 'entity' => 'RS', 'region' => 'Trebinjska regija', 'latitude' => 43.3667, 'longitude' => 17.9333, 'population' => 4000],

            // ===== BRČKO DISTRIKT =====
            ['name' => 'Brčko', 'postal_code' => '76100', 'entity' => 'BD', 'latitude' => 44.8725, 'longitude' => 18.8106, 'population' => 83516],
        ];

        foreach ($locations as $location) {
            // Generate city_slug from name for unique identification
            $citySlug = \Illuminate\Support\Str::slug($location['name']);

            // Use updateOrCreate to make seeder idempotent
            Location::updateOrCreate(
                ['city_slug' => $citySlug], // Match on city_slug
                array_merge($location, [
                    'city_slug' => $citySlug,
                    'is_active' => true, // All seeded locations are active by default
                ])
            );
        }

        $this->command->info('✅ Uspješno dodano/ažurirano ' . count($locations) . ' lokacija u BiH');
    }
}
