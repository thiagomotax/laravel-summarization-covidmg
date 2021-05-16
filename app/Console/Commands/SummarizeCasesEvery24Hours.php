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
        $this->createCasosClone();
        $this->fixZeroDates();
        $this->fixFirstValue();
        $this->fillDatesCalendar();
        $this->fillCasesDates();
        $this->fixValues2();
    }

    public function test()
    {
        $this->info(date('Y-m-d', time() - 86400));
    }

    public function createCasosClone()
    {
        //clone table and keep indexes
        $this->info('running casosClone');
        Schema::dropIfExists('casos_copy');

        DB::statement("CREATE TABLE casos_copy LIKE caso;");
        DB::statement("INSERT INTO casos_copy SELECT * FROM caso WHERE deleted_at = '0000-00-00 00:00:00';");
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

        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        foreach ($idsMunicipios as $id) {

            $queryCasos = DB::select("SELECT * FROM calendar LEFT JOIN casos_copy on datefield=dataCaso AND idMunicipio = " . $id['id'] . " GROUP BY datefield");
            $casos = json_decode(json_encode($queryCasos), true);

            foreach ($casos as $caso) {
                if ($caso["idMunicipio"] == NULL) {
                    //??????????
                    $data = $caso["datefield"][0] . $caso["datefield"][1] . $caso["datefield"][2] . $caso["datefield"][3] . $caso["datefield"][4] . $caso["datefield"][5] . $caso["datefield"][6] . $caso["datefield"][7] . $caso["datefield"][8] . $caso["datefield"][9];

                    DB::select("INSERT INTO casos_copy(
                            idMunicipio, idUsuario, dataCaso, confirmadosCaso,
                             obitosCaso, recuperadosCaso) VALUES(
                            '" . $id['id'] . "', 2, '$data', 'a', 'a', 'a')"); //'a', 'a'
                }
            }
        }
    }

    public function fixFirstValue()
    {
        $this->info('running fixFirstValue');

        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        foreach ($idsMunicipios as $id) {
            $queryCasos = DB::select("
                SELECT  *
                FROM casos_copy WHERE idMunicipio = " . $id['id'] . ".
                ");

            $casos = json_decode(json_encode($queryCasos), true);
            foreach ($casos as $caso) {
                if ($caso["dataCaso"] == '2020-02-01') {
                    if ($caso["confirmadosCaso"] == "a" && $caso["recuperadosCaso"] == "a" && $caso["obitosCaso"] == "a") //&& $caso["suspeitosCaso"] == "a" && $caso["descartadosCaso"] == "a"
                        DB::select("UPDATE casos_copy set confirmadosCaso = 0, recuperadosCaso = 0, obitosCaso = 0 WHERE idCaso = " . $caso['idCaso'] . ""); //suspeitosCaso = 0, descartadosCaso = 0
                }
            }
        }
    }

    public function fixZeroDates()
    {
        $this->info('running fixZeroDates');

        DB::select("DELETE FROM casos_copy WHERE dataCaso < '2020-02-01' ");
    }



    public function fixValues2()
    {
        //antes disso eu apago todos so casos em que o proximo seja menor que o anterior
        $this->info('running fixValues');

        $queryIds = DB::select("SELECT idMunicipio AS id FROM municipio");
        $idsMunicipios = json_decode(json_encode($queryIds), true);

        foreach ($idsMunicipios as $id) {
            $queryCasos = DB::select("SELECT * FROM casos_copy WHERE idMunicipio = '" . $id['id'] . "' AND deleted_at = '0000-00-00 00:00:00' ORDER BY dataCaso ASC");

            $casos = json_decode(json_encode($queryCasos), true);

            $previousConfirmados = null;
            $previousRecuperados = null;
            $previousObitos = null;


            foreach ($casos as $index => $caso) {
                //confirmados
                if ($caso["confirmadosCaso"] == "a") {
                    DB::select("UPDATE casos_copy SET confirmadosCaso = '" . $previousConfirmados . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }

                //recuperados
                if ($caso["recuperadosCaso"] == "a") {
                    DB::select("UPDATE casos_copy SET recuperadosCaso = '" . $previousRecuperados . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }

                //obitos
                if ($caso["obitosCaso"] == "a") {
                    DB::select("UPDATE casos_copy SET obitosCaso = '" . $previousObitos . "' WHERE idCaso =  '" . $caso['idCaso'] . "' ");
                }

                $previousConfirmados = $caso["confirmadosCaso"] != "a" ? $caso["confirmadosCaso"] : $previousConfirmados;
                $previousRecuperados = $caso["recuperadosCaso"] != "a" ? $caso["recuperadosCaso"] : $previousRecuperados;
                $previousObitos = $caso["obitosCaso"] != "a" ? $caso["obitosCaso"] : $previousObitos;
            }
        }
    }
}
