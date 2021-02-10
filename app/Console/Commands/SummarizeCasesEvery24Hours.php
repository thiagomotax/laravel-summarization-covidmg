<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Caso;
use \DB;
use \Schema;
use DateTime;

class SummarizeCasesEvery24Hours extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'case:summarize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Do a automatic summarization of database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('running');
        $this->test();

        $this->fillDatesCalendar();
        $this->fillCasesDates();
        $this->fixFirstValue();
        $this->fixZeroDates();
        $this->fixValues();
    }

    public function test(){
        $this->info(date('Y-m-d', time() - 86400));
    }

    public function fillDatesCalendar()
    {
        $this->info('running fillDatesCalendar');

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::dropIfExists('calendar');
        DB::statement("CREATE TABLE calendar(datefield DATE)");

        $firstCaseDate = new DateTime('2020-02-01');
        $firstCaseDate = $firstCaseDate->format('Y-m-d');

        $lastDayDate = date('Y-m-d', time() - 86400);
        DB::select("call FillCalendar('$firstCaseDate', '$lastDayDate')");
    }


    public function fillCasesDates()
    {
        $this->info('running fillCasesDates');

        $model = new Caso();
        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        // var_dump($idsMunicipios);
        foreach ($idsMunicipios as $id) {
            //id do responsavel pela cidade
            $model = new Caso();
            $queryIdUsuario = DB::select("SELECT idUsuario FROM caso WHERE idMunicipio = " . $id['id'] . " ORDER BY idCaso DESC LIMIT 1 ");

            if (json_decode(json_encode($queryIdUsuario), true)) {
                $userId = (json_decode(json_encode($queryIdUsuario), true))[0]['idUsuario'];
            } else {
                $userId = 2;
            }

            $queryCasos = DB::select("SELECT * FROM calendar LEFT JOIN caso on datefield=dataCaso AND idMunicipio = " . $id['id'] . " GROUP BY datefield");
            $casos = json_decode(json_encode($queryCasos), true);

            foreach ($casos as $caso) {
                if ($caso["idMunicipio"] == NULL) {
                    $data = $caso["datefield"][0] . $caso["datefield"][1] . $caso["datefield"][2] . $caso["datefield"][3] . $caso["datefield"][4] . $caso["datefield"][5] . $caso["datefield"][6] . $caso["datefield"][7] . $caso["datefield"][8] . $caso["datefield"][9];

                    //pega a ultima fonte cadastrada
                    $fonteQuery = DB::select("SELECT fonteCaso FROM caso WHERE idMunicipio = '" . $id['id'] . "' AND fonteCaso <> '' AND auto = 0 ORDER BY idMunicipio DESC LIMIT 1");
                    $fonteAux = json_decode(json_encode($fonteQuery), true);
                    $fonte =  isset($fonteAux) && isset($fonteAux[0]) && isset($fonteAux[0]['fonteCaso']) ? $fonteAux[0]['fonteCaso'] : '';

                    DB::select("INSERT INTO caso(
                            idMunicipio, idUsuario, dataCaso, confirmadosCaso,
                             obitosCaso, recuperadosCaso, suspeitosCaso, descartadosCaso, fonteCaso, auto) VALUES(
                            '" . $id['id'] . "', $userId, '$data', 'a', 'a', 'a', 'a', 'a', '$fonte', 1)");
                }
            }
        }
    }

    public function fixFirstValue()
    {
        $this->info('running fixFirstValue');

        $model = new Caso();
        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        foreach ($idsMunicipios as $id) {
            $queryCasos = DB::select("
                SELECT  *
                FROM caso WHERE idMunicipio = " . $id['id'] . ".
                ");

            $casos = json_decode(json_encode($queryCasos), true);
            foreach ($casos as $caso) {
                if ($caso["dataCaso"] == '2020-02-01') {
                    if ($caso["confirmadosCaso"] == "a" && $caso["recuperadosCaso"] == "a" && $caso["obitosCaso"] == "a" && $caso["suspeitosCaso"] == "a" && $caso["descartadosCaso"] == "a")
                        DB::select("UPDATE caso set confirmadosCaso = 0, recuperadosCaso = 0, obitosCaso = 0, suspeitosCaso = 0, descartadosCaso = 0 WHERE idCaso = " . $caso['idCaso'] . "");
                }
            }
        }
    }

    public function fixZeroDates()
    {
        $this->info('running fixZeroDates');

        $model = new Caso();
        DB::select("DELETE FROM caso WHERE dataCaso = '0000-00-00' ");
    }

    public function fixValues()
    {
        $this->info('running fixValues');

        $model = new Caso();
        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        foreach ($idsMunicipios as $id) {
            $queryCasos = DB::select("SELECT  * FROM caso WHERE idMunicipio = '" . $id['id'] . "' AND deleted_at = '0000-00-00' ORDER BY dataCaso ASC");

            $casos = json_decode(json_encode($queryCasos), true);

            $previousConfirmados = null;
            $previousRecuperados = null;
            $previousObitos = null;
            $previousDescartados = null;
            $previousSuspeitos = null;

            foreach ($casos as $key => $caso) {
                //confirmados
                if ($caso["confirmadosCaso"] == "a") {
                    $queryCasos = DB::select("UPDATE caso SET confirmadosCaso = '" . $previousConfirmados . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }
                if ($previousConfirmados > $caso['confirmadosCaso']) {
                    $queryCasos = DB::select("DELETE FROM caso WHERE idCaso = '" . $caso['idCaso'] . "' ");
                }

                //recuperados
                if ($caso["recuperadosCaso"] == "a") {
                    $queryCasos = DB::select("UPDATE caso SET recuperadosCaso = '" . $previousRecuperados . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }
                if ($previousRecuperados > $caso['recuperadosCaso']) {
                    $queryCasos = DB::select("DELETE FROM caso WHERE idCaso = '" . $caso['idCaso'] . "' ");
                }

                //obitos
                if ($caso["obitosCaso"] == "a") {
                    $queryCasos = DB::select("UPDATE caso SET obitosCaso = '" . $previousObitos . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }
                if ($previousObitos > $caso['obitosCaso']) {
                    $queryCasos = DB::select("DELETE FROM caso WHERE idCaso = '" . $caso['idCaso'] . "' ");
                }

                //suspeitos
                if ($caso["suspeitosCaso"] == "a") {
                    $queryCasos = DB::select("UPDATE caso SET suspeitosCaso = '" . $previousSuspeitos . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }
                if ($previousSuspeitos > $caso['suspeitosCaso']) {
                    $queryCasos = DB::select("DELETE FROM caso WHERE idCaso = '" . $caso['idCaso'] . "' ");
                }

                //descartados
                if ($caso["descartadosCaso"] == "a") {
                    $queryCasos = DB::select("UPDATE caso SET descartadosCaso = '" . $previousDescartados . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }
                if ($previousDescartados > $caso['descartadosCaso']) {
                    $queryCasos = DB::select("DELETE FROM caso WHERE idCaso = '" . $caso['idCaso'] . "' ");
                }


                $previousConfirmados = $caso["confirmadosCaso"] != "a" ? $caso["confirmadosCaso"] : $previousConfirmados;
                $previousRecuperados = $caso["recuperadosCaso"] != "a" ? $caso["recuperadosCaso"] : $previousRecuperados;
                $previousObitos = $caso["obitosCaso"] != "a" ? $caso["obitosCaso"] : $previousObitos;
                $previousSuspeitos = $caso["suspeitosCaso"] != "a" ? $caso["suspeitosCaso"] : $previousSuspeitos;
                $previousDescartados = $caso["descartadosCaso"] != "a" ? $caso["descartadosCaso"] : $previousDescartados;
            }
        }
    }
}
