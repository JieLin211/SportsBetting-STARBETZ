<?php

namespace App\Console\Commands;

use App\Http\Traits\Notify;
use App\Models\BetInvest;
use App\Models\GameCategory;
use App\Models\GameTournament;
use App\Models\GameTeam;
use App\Models\GameMatch;
use App\Models\GameQuestions;
use App\Models\GameOption;
use App\Models\ContentOdd;
use Illuminate\Console\Command;
use Carbon\Carbon;
use Facades\App\Services\BasicService;
use App\Http\Controllers\Admin\ContentController;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;


class FetchMatch extends Command
{
    use Notify;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cron:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cron for fetch odd api';

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
        $now = Carbon::now();
        $basic = (object)config('basic');

        $this->createCategories();
        $this->createTournaments();
        $this->createTeams();
        $this->createMatches();

        $this->info('status');
    }

    public function createCategories()
    {
        $contentCtrl = new ContentController();
        $content = $contentCtrl->fetchFromOdds('/sports', '');
        if ($content == null) return;

        $sports = json_decode($content);
        $added_categories = GameCategory::orderBy('id', 'desc')->get()->pluck('name')->toArray();
        $games = config('games');
        $icon = $games['Normal'];

        foreach ($sports as &$sport) {
            $exist = array_search($sport->group, $added_categories);
            if ($exist !== false) continue;

            $gameCategory = new GameCategory();
            $gameCategory->name = $sport->group;
            $gameCategory->icon = $icon;
            $gameCategory->status = $sport->active;

            $gameCategory->save();
            Log::info('Game Category => ' . $sport->group . '|' . $sport->active . ' - Saved.');
        }
    }

    public function createTournaments()
    {
        $contentCtrl = new ContentController();
        $content = $contentCtrl->fetchFromOdds('/sports', '');
        if ($content == null) return;

        $sports = json_decode($content);
        $added_tournaments = GameTournament::orderBy('id', 'desc')->get()->pluck('name')->toArray();
        $categories = GameCategory::whereStatus(1)->orderBy('name','asc')->get()->toArray();

        $tournaments = [];
        foreach ($sports as &$sport) {
            $exist = array_search($sport->title, $added_tournaments);
            if ($exist !== false) continue;

            $id = array_search($sport->group, array_column($categories, 'name'));
            if ($id === false) continue;

            $gameTournament = new GameTournament();
            $gameTournament->name = $sport->title;
            $gameTournament->category_id = $categories[$id]['id'];
            $gameTournament->status = $sport->active;

            $gameTournament->save();
            Log::info('Tournament => ' . $sport->title . '|'. $categories[$id]['name'] . '|' . $sport->active . ' - Saved.');
        }
    }

    public function createTeams()
    {
        $contentCtrl = new ContentController();
        $content = $contentCtrl->fetchFromOdds('/sports/upcoming/odds', 'regions=us,us2,uk,eu,au&markets=h2h,totals,spreads');
        if ($content == null) return;

        $matches = json_decode($content);
        $added_teams = GameTeam::orderBy('id', 'desc')->get()->pluck('name')->toArray();
        $tours = GameTournament::with('gameCategory')->orderBy('id', 'desc')->get()->toArray();
    
        $teams = [];
        foreach ($matches as &$match) {
            $tour_id = array_search($match->sport_title, array_column($tours, 'name'));
            if ($tour_id === false) continue;

            $tour = $tours[$tour_id];
            $category = $tour['game_category'];


            $exist = array_search($match->home_team, $added_teams);
            if ($exist !== false) continue;

            $gameTeam = new GameTeam();
            $gameTeam->name = $match->home_team;
            $gameTeam->category_id = $category['id'];
            $gameTeam->image = '';
            $gameTeam->status = 1;

            $gameTeam->save();
            Log::info('Game Team => ' . $match->home_team . '|'. $category['name'] . ' - Saved.');


            $exist = array_search($match->away_team, $added_teams);
            if ($exist !== false) continue;

            $gameTeam = new GameTeam();
            $gameTeam->name = $match->away_team;
            $gameTeam->category_id = $category['id'];
            $gameTeam->image = '';
            $gameTeam->status = 1;

            $gameTeam->save();
            Log::info('Game Team => ' . $match->away_team . '|'. $category['name'] . ' - Saved.');
        }
    }

    public function createMatches()
    {
        $contentCtrl = new ContentController();
        $content = $contentCtrl->fetchFromOdds('/sports/upcoming/odds', 'regions=us,us2,uk,eu,au&markets=h2h,totals,spreads');
        if ($content == null) return;

        $matches = json_decode($content);
        $tours = GameTournament::with('gameCategory')->orderBy('id', 'desc')->get()->toArray();

        $to_add_matches = [];
        foreach ($matches as &$match) {
            $tour_id = array_search($match->sport_title, array_column($tours, 'name'));
            if ($tour_id === false) continue;

            $tour = $tours[$tour_id];
            $category = $tour['game_category'];

            $teams = GameTeam::where('category_id', $tour['category_id'])->orderBy('id', 'desc')->get()->toArray();

            $home_id = array_search($match->home_team, array_column($teams, 'name'));
            if ($home_id === false) continue;
            $home_team = $teams[$home_id];

            $away_id = array_search($match->away_team, array_column($teams, 'name'));
            if ($away_id === false) continue;
            $away_team = $teams[$away_id];

            $gameMatch = new GameMatch();
            $gameMatch->category_id = $category['id'];
            $gameMatch->tournament_id = $tour['id'];
            $gameMatch->team1_id = $home_team['id'];
            $gameMatch->team2_id = $away_team['id'];
            $gameMatch->start_date = $match->commence_time;
            $end_time = date('Y-m-d\Th:i:sZ', strtotime($match->commence_time. ' + 1 days'));
            $gameMatch->end_date = $end_time;
            $gameMatch->status = 1;

            $gameMatch->save();
            Log::info('Game Match => ' . $home_team['name'] . ':' . $away_team['name'] . '|'. $tour['name'] . '|' . $match->commence_time . ' - Saved.');

            
            $this->storeQuestionsFromOdd($gameMatch->id, $match, $end_time);
        }

    }

    
    public function storeQuestionsFromOdd($match_id, $details, $end_time)
    {
        $home_team = $details->home_team;
        $away_team = $details->away_team;

        $h2h = [[
            "name" => $home_team,
            "price" => 0,
        ], [
            "name" => $away_team,
            "price" => 0,
        ], [
            "name" => "Draw",
            "price" => 0,
        ]];

        $spreads = [[
            "name" => $home_team,
            "price" => 0,
            "point" => 0,
        ], [
            "name" => $away_team,
            "price" => 0,
            "point" => 0,
        ]];
        
        $totals = [[
            "name" => 'Over',
            "price" => 0,
            "point" => 0,
        ], [
            "name" => 'Under',
            "price" => 0,
            "point" => 0,
        ]];

        $h2h_count = 0;
        $spreads_count = 0;
        $totals_count = 0;
        
        $bookmakers = $details->bookmakers;
        foreach ($bookmakers as $bookmaker)
        {
            $markets = $bookmaker->markets;
            
            foreach ($markets as $market)
            {
                if ($market->key == 'h2h') {
                    $h2h_count++;
                    $h2h = $this->average($h2h, $market->outcomes, $h2h_count);
                }

                if ($market->key == 'spreads') {
                    $spreads_count++;
                    $spreads = $this->average($spreads, $market->outcomes, $spreads_count);
                }

                if ($market->key == 'totals') {
                    $totals_count++;
                    $totals = $this->average($totals, $market->outcomes, $totals_count);
                }
            }
        }

        $this->storeQuestionFrom($match_id, $h2h, 'MoneyLine', $end_time);
        $this->storeQuestionFrom($match_id, $spreads, 'Handicaps', $end_time);
        $this->storeQuestionFrom($match_id, $totals, 'Over / Under', $end_time);
    }

    public function average($arr1, $arr2, $count)
    {
        $result = [];
        foreach ($arr1 as $item1)
        {
            $item = [];
            if (count($item1) < 1) continue;
            foreach ($arr2 as $item2)
            {
                $item2 = json_decode(json_encode($item2), true);
                if (count($item2) < 1) continue;
                if ($item1['name'] != $item2['name']) continue;

                $item['name'] = $item1['name'];

                if (isset($item2['point']))
                    $item['point'] = number_format($item2['point'] ? ($item1['point'] * ($count - 1) + $item2['point']) / $count : 0, 2);

                if (isset($item2['price']))
                    $item['price'] = number_format($item2['price'] ? ($item1['price'] * ($count - 1) + $item2['price']) / $count : 0, 2);

                break;
            }

            array_push($result, $item);
        }

        return $result;
    }

    public function storeQuestionFrom($match_id, $data, $title, $end_time)
    {
        $betQues = new GameQuestions();
        $betQues->match_id = $match_id;
        $betQues->creator_id = 1;
        $betQues->name = $title;
        $betQues->status = 1;
        $betQues->end_time = $end_time;
        $betQues->save();
        Log::info('Match Quiz => ' . $title . ' - Saved.');

        foreach ($data as $item) {
            $betOpt = new GameOption();
            $betOpt->creator_id = 1;
            $betOpt->question_id = $betQues->id;
            $betOpt->match_id = $betQues->match_id;
            $betOpt->option_name = $item['name'];
            $betOpt->ratio = $item['price'];
            $betOpt->status = 1;
            $betOpt->save();
            Log::info('Match Quiz Opt. => ' . $item['name'] . '|' . $item['price'] . ' - Saved.');
        }
    }

}
