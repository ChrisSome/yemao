<?php
namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\HttpController\User\WebSocket;
use App\lib\FrontService;
use App\Model\AdminAlphaMatch;
use App\Model\AdminClashHistory;
use App\Model\AdminCompetition;
use App\Model\AdminCompetitionRuleList;
use App\Model\AdminHonorList;
use App\Model\AdminManagerList;
use App\Model\AdminMatch;
use App\Storage\OnlineUser;
use App\Model\SeasonAllTableDetail;
use App\Model\AdminMatchTlive;
use App\Model\AdminNoticeMatch;
use App\Model\AdminPlayer;
use App\Model\AdminPlayerChangeClub;
use App\Model\AdminPlayerHonorList;
use App\Model\AdminPlayerStat;
use App\Model\AdminSeason;
use App\Model\AdminStageList;
use App\Model\AdminSteam;
use App\Model\AdminTeam;
use App\Model\AdminTeamHonor;
use App\Model\AdminTeamLineUp;
use App\Model\AdminUser;
use App\Model\AdminUserSetting;
use App\Model\SeasonMatchList;
use App\Model\SeasonTeamPlayer;
use App\Task\MatchNotice;
use App\Utility\Log\Log;
use App\lib\Tool;
use App\Utility\Message\Status;
use App\GeTui\BatchSignalPush;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;

/**
 *                             _ooOoo_
 *                            o8888888o
 *                            88" . "88
 *                            (| -_- |)
 *                            O\  =  /O
 *                         ____/`---'\____
 *                       .'  \\|     |//  `.
 *                      /  \\|||  :  |||//  \
 *                     /  _||||| -:- |||||-  \
 *                     |   | \\\  -  /// |   |
 *                     | \_|  ''\---/''  |   |
 *                     \  .-\__  `-`  ___/-. /
 *                   ___`. .'  /--.--\  `. . __
 *                ."" '<  `.___\_<|>_/___.'  >'"".
 *               | | :  `- \`.;`\ _ /`;.`/ - ` : | |
 *               \  \ `-.   \_ __\ /__ _/   .-` /  /
 *          ======`-.____`-.___\_____/___.-`____.-'======
 *                             `=---='
 *          ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
 *                     ????????????        ??????BUG
 */
class Crontab extends FrontUserController
{
    const STATUS_SUCCESS = 0; //????????????
    protected $isCheckSign = false;
    public $needCheckToken = false;
    public $start_time = 0;

    public $taskData = [];
    protected $user = 'mark9527';

    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';

    protected $url = 'https://open.sportnanoapi.com';

    protected $uriTeamList = '/api/v4/football/team/list?user=%s&secret=%s&time=%s';  //????????????

    protected $uriM = 'https://open.sportnanoapi.com/api/v4/football/match/diary?user=%s&secret=%s&date=%s';
    protected $uriCompetition = '/api/v4/football/competition/list?user=%s&secret=%s&time=%s';


    protected $uriSteam = '/api/sports/stream/urls_free?user=%s&secret=%s'; //????????????
    protected $uriLineUp = '/api/v4/football/team/squad/list?user=%s&secret=%s&time=%s';  //??????
    protected $uriPlayer = '/api/v4/football/player/list?user=%s&secret=%s&time=%s';  //??????
    protected $uriCompensation = '/api/v4/football/compensation/list?user=%s&secret=%s&time=%s';  //??????????????????????????????????????????
    protected $live_url = 'https://open.sportnanoapi.com/api/sports/football/match/detail_live?user=%s&secret=%s';//????????????
    protected $season_url = 'https://open.sportnanoapi.com/api/v4/football/season/list?user=%s&secret=%s&time=%s'; //????????????
    protected $player_stat = 'https://open.sportnanoapi.com/api/v4/football/player/list/with_stat?user=%s&secret=%s&time=%s'; //??????????????????????????????
    protected $player_change_club_history = 'https://open.sportnanoapi.com/api/v4/football/transfer/list?user=%s&secret=%s&time=%s'; //??????????????????
    protected $team_honor = 'https://open.sportnanoapi.com/api/v4/football/team/honor/list?user=%s&secret=%s&id=%s'; //????????????
    protected $honor_list = 'https://open.sportnanoapi.com/api/v4/football/honor/list?user=%s&secret=%s&time=%s'; //????????????
    protected $all_stat = 'https://open.sportnanoapi.com/api/v4/football/season/all/stats/detail?user=%s&secret=%s&id=%s'; //????????????????????????????????????-??????
    protected $stage_list = 'https://open.sportnanoapi.com/api/v4/football/stage/list?user=%s&secret=%s&time=%s'; //??????????????????
    protected $manager_list = 'https://open.sportnanoapi.com/api/v4/football/manager/list?user=%s&secret=%s&time=%s'; //??????

    protected $uriDeleteMatch = '/api/v4/football/deleted?user=%s&secret=%s'; //????????????????????????
    protected $player_honor_list = 'https://open.sportnanoapi.com/api/v4/football/player/honor/list?user=%s&secret=%s&time=%s'; //????????????????????????
    protected $trend_detail = 'https://open.sportnanoapi.com/api/v4/football/match/trend/detail?user=%s&secret=%s&id=%s'; //????????????????????????
    protected $competition_rule = 'https://open.sportnanoapi.com/api/v4/football/competition/rule/list?user=%s&secret=%s&time=%s'; //????????????????????????
    protected $history = 'https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s'; //??????????????????
    protected $season_all_table_detail = 'https://open.sportnanoapi.com/api/v4/football/season/all/table/detail?user=%s&secret=%s&id=%s'; //???????????????????????????-??????


    protected $changingMatch = 'https://open.sportnanoapi.com/api/v4/football/match/list?user=%s&secret=%s&time=%s';  //????????????


    /**
     * ??????????????????  ????????????
     */
    function getTeamList()
    {

        $time_stamp = AdminTeam::create()->max('updated_at');
        $url = sprintf($this->url . $this->uriTeamList, $this->user, $this->secret, $time_stamp + 1);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        if ($teams['query']['total'] == 0) {
            return;
        }
        $decodeTeams = $teams['results'];
        foreach ($decodeTeams as $team) {
            $data = [
                'team_id' => $team['id'],
                'competition_id' => $team['competition_id'],
                'country_id' => isset($team['country_id']) ? $team['country_id'] : 0,
                'name_zh' => $team['name_zh'],
                'short_name_zh' => $team['short_name_zh'],
                'name_en' => $team['name_en'],
                'short_name_en' => $team['short_name_en'],
                'logo' => isset($team['logo']) ? $team['logo'] : '',
                'national' => $team['national'],
                'foundation_time' => $team['foundation_time'],
                'website' => isset($team['website']) ? $team['website'] : '',
                'manager_id' => $team['manager_id'],
                'venue_id' => isset($team['venue_id']) ? $team['venue_id'] : 0,
                'market_value' => isset($team['market_value']) ? $team['market_value'] : '',
                'market_value_currency' => isset($team['market_value_currency']) ? $team['market_value_currency'] : '',
                'country_logo' => isset($team['country_logo']) ? $team['country_logo'] : '',
                'total_players' => isset($team['total_players']) ? $team['total_players'] : 0,
                'foreign_players' => isset($team['foreign_players']) ? $team['foreign_players'] : 0,
                'national_players' => isset($team['national_players']) ? $team['national_players'] : 0,
                'updated_at' => $team['updated_at'],
            ];
            $exist = AdminTeam::create()->where('team_id', $team['id'])->get();
            if ($exist) {
                unset($data['team_id']);
                AdminTeam::create()->update($data, ['team_id' => $team['id']]);
            } else {
                AdminTeam::create($data)->save();

            }

        }

    }


    public function updateChangingMatch()
    {
        $time = AdminMatch::create()->max('updated_at');
        $url = sprintf($this->changingMatch, $this->user, $this->secret, $time);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . ' ???????????????');
            return;
        }

        foreach ($decodeDatas as $data) {
            //???????????????????????? ????????????????????????????????????????????????
            if ($signal_season_match = SeasonMatchList::create()->where('match_id', $data['id'])->get()) {
                $signal_season_match->home_scores = json_encode($data['home_scores']);
                $signal_season_match->away_scores = json_encode($data['away_scores']);
                $signal_season_match->home_position = $data['home_position'];
                $signal_season_match->away_position = $data['away_position'];
                $signal_season_match->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal_season_match->status_id = $data['status_id'];
                $signal_season_match->updated_at = $data['updated_at'];
                $signal_season_match->match_time = $data['match_time'];
                $signal_season_match->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                $signal_season_match->referee_id = isset($data['referee_id']) ? intval($data['referee_id']) : 0;
                $signal_season_match->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal_season_match->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal_season_match->update();
            } else {
                //?????????????????????
                $insertData = [
                    'match_id' => $data['id'],
                    'competition_id' => $data['competition_id'],
                    'home_team_id' => $data['home_team_id'],
                    'away_team_id' => $data['away_team_id'],
                    'match_time' => $data['match_time'],
                    'neutral' => $data['neutral'],
                    'note' => $data['note'],
                    'season_id' => $data['season_id'],
                    'home_scores' => json_encode($data['home_scores']),
                    'away_scores' => json_encode($data['away_scores']),
                    'home_position' => $data['home_position'],
                    'away_position' => $data['away_position'],
                    'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                    'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                    'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                    'round' => isset($data['round']) ? json_encode($data['round']) : '',
                    'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                    'status_id' => $data['status_id'],
                    'updated_at' => $data['updated_at'],
                ];
                SeasonMatchList::create($insertData)->save();
            }
            if ($signal = AdminMatch::create()->where('match_id', $data['id'])->get()) {
                if ($signal->status_id == 8) continue;
                $signal->home_scores = json_encode($data['home_scores']);
                $signal->away_scores = json_encode($data['away_scores']);
                $signal->home_position = $data['home_position'];
                $signal->away_position = $data['away_position'];
                $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal->status_id = $data['status_id'];
                $signal->updated_at = $data['updated_at'];
                $signal->match_time = $data['match_time'];
                $signal->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                $signal->referee_id = isset($data['referee_id']) ? intval($data['referee_id']) : 0;
                $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                $signal->update();

            } else {

                $insertData = [
                    'match_id' => $data['id'],
                    'competition_id' => $data['competition_id'],
                    'home_team_id' => $data['home_team_id'],
                    'away_team_id' => $data['away_team_id'],
                    'match_time' => $data['match_time'],
                    'neutral' => $data['neutral'],
                    'note' => $data['note'],
                    'season_id' => $data['season_id'],
                    'home_scores' => json_encode($data['home_scores']),
                    'away_scores' => json_encode($data['away_scores']),
                    'home_position' => $data['home_position'],
                    'away_position' => $data['away_position'],
                    'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                    'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                    'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                    'round' => isset($data['round']) ? json_encode($data['round']) : '',
                    'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                    'status_id' => $data['status_id'],
                    'updated_at' => $data['updated_at'],
                ];

                AdminMatch::create($insertData)->save();

            }


        }
        Log::getInstance()->info('????????????????????????');
    }




    /*
     * ??????????????????????????????????????????????????????????????????????????????????????????
     */
    public function updateSeasonTeamPlayer()
    {
        //????????????
        $seasons = AdminSeason::create()->field(['season_id'])->where('is_current', 1)->where('has_player_stats', 1)->all();
        //???????????????
        foreach ($seasons as $data) {
            if ($data['season_id']) {
                $seasonId = $data['season_id'];
                //??????????????????????????????
                $url = sprintf($this->all_stat, $this->user, $this->secret, $seasonId);
                $res = Tool::getInstance()->postApi($url);
                $decode = json_decode($res, true);
                $results = !empty($decode['results']) ? $decode['results'] : [];
                if ($results) {
                    if ($seasonTeamPlayerRes = SeasonTeamPlayer::create()->where('season_id', $seasonId)->get()) {
                        $seasonTeamPlayerRes->upadted_at = $results['updated_at'];
                        $seasonTeamPlayerRes->players_stats = !empty($results['players_stats']) ? json_encode($results['players_stats']) : json_encode([]);
                        $seasonTeamPlayerRes->shooters = !empty($results['shooters']) ? json_encode($results['shooters']) : json_encode([]);
                        $seasonTeamPlayerRes->teams_stats = !empty($results['teams_stats']) ? json_encode($results['teams_stats']) : json_encode([]);
                        $seasonTeamPlayerRes->update();
                    } else {
                        $insertDataOne = [
                            'updated_at' => $results['updated_at'],
                            'players_stats' => !empty($results['players_stats']) ? json_encode($results['players_stats']) : json_encode([]),
                            'shooters' => !empty($results['shooters']) ? json_encode($results['shooters']) : json_encode([]),
                            'teams_stats' => !empty($results['teams_stats']) ? json_encode($results['teams_stats']) : json_encode([]),
                            'season_id' => $seasonId
                        ];
                        SeasonTeamPlayer::create($insertDataOne)->save();
                    }
                }


                //???????????????
                $url = sprintf($this->season_all_table_detail, $this->user, $this->secret, $seasonId);
                $res = Tool::getInstance()->postApi($url);
                $decode = json_decode($res, true);
                $decodeTable = $decode['results'];
                if (!empty($decode['results'])) {
                    if ($seasonTable = SeasonAllTableDetail::create()->where('season_id', $seasonId)->get()) {
                        $seasonTable->promotions = !empty($decodeTable['promotions']) ? json_encode($decodeTable['promotions']) : '';
                        $seasonTable->tables = !empty($decodeTable['tables']) ? json_encode($decodeTable['tables']) : '';
                        $seasonTable->update();
                    } else {
                        $insertTable = [
                            'promotions' => !empty($decodeTable['promotions']) ? json_encode($decodeTable['promotions']) : '',
                            'tables' => !empty($decodeTable['tables']) ? json_encode($decodeTable['tables']) : '',
                            'season_id' => $seasonId
                        ];
                        SeasonAllTableDetail::create($insertTable)->save();
                    }
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }



    /**
     * ???????????????????????? 30 min / time
     */
    function getWeekMatches()
    {
        $weeks = FrontService::getWeek();
        foreach ($weeks as $week) {
            $url = sprintf($this->uriM, $this->user, $this->secret, $week);
            $res = Tool::getInstance()->postApi($url);
            $teams = json_decode($res, true);
            $decodeDatas = $teams['results'];
            if (!$decodeDatas) {
                return;
            }
            foreach ($decodeDatas as $data) {

                if ($signal = AdminMatch::create()->where('match_id', $data['id'])->get()) {
                    $signal->home_scores = json_encode($data['home_scores']);
                    $signal->away_scores = json_encode($data['away_scores']);
                    $signal->home_position = $data['home_position'];
                    $signal->away_position = $data['away_position'];
                    $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                    $signal->status_id = $data['status_id'];
                    $signal->updated_at = $data['updated_at'];
                    $signal->match_time = $data['match_time'];
                    $signal->coverage = isset($data['coverage']) ? json_encode($data['coverage']) : '';
                    $signal->referee_id = isset($data['referee_id']) ? json_encode($data['referee_id']) : 0;
                    $signal->round = isset($data['round']) ? json_encode($data['round']) : '';
                    $signal->environment = isset($data['environment']) ? json_encode($data['environment']) : '';
                    $signal->update();

                } else {
                    $insertData = [
                        'match_id' => $data['id'],
                        'competition_id' => $data['competition_id'],
                        'home_team_id' => $data['home_team_id'],
                        'away_team_id' => $data['away_team_id'],
                        'match_time' => $data['match_time'],
                        'neutral' => $data['neutral'],
                        'note' => $data['note'],
                        'season_id' => $data['season_id'],
                        'home_scores' => json_encode($data['home_scores']),
                        'away_scores' => json_encode($data['away_scores']),
                        'home_position' => $data['home_position'],
                        'away_position' => $data['away_position'],
                        'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                        'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                        'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                        'round' => isset($data['round']) ? json_encode($data['round']) : '',
                        'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                        'status_id' => $data['status_id'],
                        'updated_at' => $data['updated_at'],
                    ];

                    AdminMatch::create($insertData)->save();
                }
            }
        }

    }

    /**
     * one day / time ????????????
     */
    function getCompetitiones()
    {
        $max_updated_at = AdminCompetition::create()->max('updated_at');
        $url = sprintf($this->url . $this->uriCompetition, $this->user, $this->secret, $max_updated_at + 1);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        if (!$teams['results']) {
            Log::getInstance()->info(date('Y-m-d H:i:s') . ' ????????????');
            return;
        }
        $datas = $teams['results'];

        foreach ($datas as $data) {
            $insertData = [
                'competition_id' => $data['id'],
                'category_id' => $data['category_id'],
                'country_id' => $data['country_id'],
                'name_zh' => $data['name_zh'],
                'short_name_zh' => $data['short_name_zh'],
                'type' => $data['type'],
                'cur_season_id' => $data['cur_season_id'],
                'cur_stage_id' => $data['cur_stage_id'],
                'cur_round' => $data['cur_round'],
                'round_count' => $data['round_count'],
                'logo' => $data['logo'],
                'title_holder' => isset($data['title_holder']) ? json_encode($data['title_holder']) : '',
                'most_titles' => isset($data['most_titles']) ? json_encode($data['most_titles']) : '',
                'newcomers' => isset($data['newcomers']) ? json_encode($data['newcomers']) : '',
                'divisions' => isset($data['divisions']) ? json_encode($data['divisions']) : '',
                'host' => isset($data['host']) ? json_encode($data['host']) : '',
                'primary_color' => isset($data['primary_color']) ? $data['primary_color'] : '',
                'secondary_color' => isset($data['secondary_color']) ? $data['secondary_color'] : '',
                'updated_at' => $data['updated_at'],
            ];
            $exist = AdminCompetition::create()->where('competition_id', $data['id'])->get();
            if ($exist) {
                unset($insertData['competition_id']);
                AdminCompetition::create()->update($insertData, ['competition_id' => $data['id']]);
            } else {
                AdminCompetition::create($insertData)->save();
            }
        }
    }


    /**
     * ????????????  10min/???
     */
    public function getSteam()
    {
        $url = sprintf($this->url . $this->uriSteam, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $steam = json_decode($res, true)['data'];

        if (!$steam) {
            return;
        }
        foreach ($steam as $item) {
            $data = [
                'sport_id' => $item['sport_id'],
                'match_id' => $item['match_id'],
                'match_time' => $item['match_time'],
                'comp' => $item['comp'],
                'home' => $item['home'],
                'away' => $item['away'],
                'mobile_link' => $item['mobile_link'],
                'pc_link' => $item['pc_link'],
            ];

            if (AdminSteam::create()->where('match_id', $item['match_id'])->get()) {
                AdminSteam::create()->update($data, ['match_id' => $item['match_id']]);
            } else {
                AdminSteam::create($data)->save();

            }
        }
        Log::getInstance()->info('???????????????????????????');

    }


    /**
     * ??????  one hour / time
     */
    public function getLineUp()
    {
        $time = AdminTeamLineUp::create()->max('updated_at');
        $url = sprintf($this->url . $this->uriLineUp, $this->user, $this->secret, $time);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if (!$resp['results']) {
            return $this->writeJson(Status::CODE_OK, '????????????');

        }
        foreach ($resp['results'] as $item) {
            $inert = [
                'team_id' => $item['id'],
                'team' => json_encode($item['team']),
                'squad' => json_encode($item['squad']),
                'updated_at' => $item['updated_at'],
            ];
            if (AdminTeamLineUp::create()->where('team_id', $item['id'])->get()) {
                AdminTeamLineUp::create()->update($inert, ['team_id' => $item['id']]);
            } else {
                AdminTeamLineUp::create($inert)->save();
            }
        }

    }


    /**
     * ??????????????????  one day / time
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function players()
    {
        $max_updated_at = AdminPlayer::create()->max('updated_at');
        $url = sprintf($this->url . $this->uriPlayer, $this->user, $this->secret, $max_updated_at + 1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return;
            } else {
                foreach ($resp['results'] as $item) {
                    $inert = [
                        'player_id' => $item['id'],
                        'team_id' => $item['team_id'],
                        'birthday' => $item['birthday'],
                        'age' => $item['age'],
                        'weight' => $item['weight'],
                        'height' => $item['height'],
                        'nationality' => $item['nationality'],
                        'market_value' => $item['market_value'],
                        'market_value_currency' => $item['market_value_currency'],
                        'contract_until' => $item['contract_until'],
                        'position' => $item['position'],
                        'name_zh' => $item['name_zh'],
                        'name_en' => $item['name_en'],
                        'logo' => $item['logo'],
                        'country_id' => $item['country_id'],
                        'preferred_foot' => $item['preferred_foot'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (AdminPlayer::create()->where('player_id', $item['id'])->get()) {
                        AdminPlayer::create()->update($inert, ['player_id' => $item['id']]);
                    } else {
                        AdminPlayer::create($inert)->save();

                    }
                }
            }
        } else {
            return;
        }


    }

    /**
     * ??????????????????
     * ??????????????????????????????
     */
    public function clashHistory()
    {
        $timestamp = AdminClashHistory::create()->max('updated_at');
        $url = sprintf($this->url . $this->uriCompensation, $this->user, $this->secret, $timestamp + 1);
        $res = json_decode(Tool::getInstance()->postApi($url), true);
        if ($res['code'] == 0) {
            if ($res['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, '????????????');

            } else {
                foreach ($res['results'] as $item) {
                    $insert = [
                        'match_id' => $item['id'],
                        'history' => json_encode($item['history']),
                        'recent' => json_encode($item['recent']),
                        'similar' => json_encode($item['similar']),
                        'updated_at' => $item['updated_at'],
                    ];
                    if (AdminClashHistory::create()->where('match_id', $item['id'])->get()) {
                        AdminClashHistory::create()->update($insert, ['match_id' => $item['id']]);
                    } else {
                        AdminClashHistory::create($insert)->save();
                    }
                }
            }

        } else {
            return;

        }


    }


    /**
     * ???????????????
     * ???????????????????????????????????? ????????????????????????
     */
    public function noticeUserMatch()
    {
        $matches = AdminMatch::create()->where('match_time', time() + 60 * 15, '>')->where('match_time', time() + 60 * 16, '<=')->where('status_id', 1)->all();
        if ($matches) {
            foreach ($matches as $match) {
                if (AdminNoticeMatch::create()->where('match_id', $match->id)->where('is_notice', 1)->get()) {
                    continue;
                }
                if (!$prepareNoticeUserIds = AppFunc::getUsersInterestMatch($match->match_id)) {
                    continue;
                } else {
                    $users = AdminUser::create()->where('id', $prepareNoticeUserIds, 'in')->field(['cid', 'id'])->all();

                    foreach ($users as $k => $user) {
                        $userSetting = AdminUserSetting::create()->where('user_id', $user['id'])->get();
                        $startSetting = json_decode($userSetting->push, true)['start'];
                        if (!$userSetting || !$startSetting) {
                            unset($users[$k]);
                        }
                    }
                    $uids = array_column($users, 'id');
                    $cids = array_column($users, 'cid');

                    if (!$uids) {
                        return;
                    }

                    $title = '????????????';
                    $content = sprintf('???????????????%s?????????%s-%s??????5???????????????????????????????????????', $match->competitionName()->short_name_zh, $match->homeTeamName()->name_zh, $match->awayTeamName()->name_zh);;
                    $batchPush = new BatchSignalPush();
                    $insertData = [
                        'uids' => json_encode($uids),
                        'match_id' => $match->match_id,
                        'type' => 10,
                        'title' => $title,
                        'content' => $content,
                        'item_type' => 1
                    ];
                    if (!$res = AdminNoticeMatch::create()->where('match_id', $match->match_id)->where('type', 10)->get()) {
                        $rs = AdminNoticeMatch::create($insertData)->save();
                        $info['rs'] = $rs;  //????????????
                        $pushInfo = [
                            'title' => $title,
                            'content' => $content,
                            'payload' => ['item_id' => $match->match_id, 'item_type' => 1],
                            'notice_id' => $rs,

                        ];
                        $batchPush->pushMessageToList($cids, $pushInfo);

                    }
                }


            }
        } else {
            Log::getInstance()->info('no match to notice');
        }
    }

    /**
     * ???????????????????????????   5min/???
     */
    public function deleteMatch()
    {
        $url = sprintf($this->url . $this->uriDeleteMatch, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            $dMatches = $resp['results']['match'];
            if ($dMatches) {

                foreach ($dMatches as $dMatch) {
                    if ($match = AdminMatch::create()->where('match_id', $dMatch)->get()) {
                        $match->is_delete = 1;
                        $match->update();
                    }
                }
            }
        }

        Log::getInstance()->info(date('Y-m-d H:i:s') . ' ???????????????????????????');


    }

    /**
     * alpha match ?????????????????????one minute/time
     */
    public function updateAlphaMatch()
    {
        $params = [
            'matchType' => 'football',
            'matchDate' => date('Ymd')
        ];
        $header = [
            'xcode: ty019'
        ];
        $res = Tool::getInstance()->postApi('http://www.xsports-live.com:8086/live/sport/getLiveInfo', 'GET', $params, $header);
        $decode = json_decode($res, true);
        if ($decode['code'] == 200) {
            $decode_data = $decode['data'];

            foreach ($decode_data as $datum) {
                $data['timeFormart'] = $datum['timeFormart'];
                $data['ligaEn'] = $datum['ligaEn'];
                $data['teams'] = $datum['teams'];
                $data['liga'] = $datum['liga'];
                $data['sportType'] = $datum['sportType'];
                $data['teamsEn'] = $datum['teamsEn'];
                $data['matchTime'] = $datum['matchTime'];
                $data['liveUrl2'] = $datum['liveUrl2'];
                $data['liveUrl3'] = $datum['liveUrl3'];
                $data['liveUrl'] = $datum['liveUrl'];
                $data['matchId'] = $datum['matchId'];
                $data['liveStatus'] = $datum['liveStatus'];
                $data['status'] = $datum['status'];

                if ($signal = AdminAlphaMatch::create()->where('matchId', $datum['matchId'])->get()) {
                    $signal->matchTime = $datum['matchTime'];
                    $signal->liveUrl2 = $datum['liveUrl2'];
                    $signal->liveUrl3 = $datum['liveUrl3'];
                    $signal->liveUrl = $datum['liveUrl'];
                    $signal->liveStatus = $datum['liveStatus'];
                    $signal->status = $datum['status'];
                    $signal->update();
                } else {
                    AdminAlphaMatch::create($data)->save();
                }

            }
        }
    }






    public function fixMatch()
    {

        $match_id = $this->params['match_id'];
        $url = sprintf('https://open.sportnanoapi.com/api/v4/football/match/live/history?user=%s&secret=%s&id=%s', $this->user, $this->secret, $match_id);

        $res = Tool::getInstance()->postApi($url);
        $decode = json_decode($res, true);
        $decodeDatas = $decode['results'];

        if (!$decodeDatas) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 2);

        }
        $match = AdminMatch::create()->where('match_id', $match_id)->get();
        $statusId = $decodeDatas['score'][1];
        $match->home_scores = json_encode($decodeDatas['score'][2]);
        $match->away_scores = json_encode($decodeDatas['score'][3]);
        $match->status_id = $decodeDatas['score'][1];
        $match->update();
        //????????????
        $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $match_id));
        $match_trend = json_decode($match_res, true);
        if ($match_trend['code'] != 0) {
            $match_trend_info = [];
        } else {
            $match_trend_info = $match_trend['results'];
        }
        $match_tlive_data = [
            'stats' => isset($decodeDatas['stats']) ? json_encode($decodeDatas['stats']) : '',
            'score' => isset($decodeDatas['score']) ? json_encode($decodeDatas['score']) : '',
            'incidents' => isset($decodeDatas['incidents']) ? json_encode($decodeDatas['incidents']) : '',
            'tlive' => isset($decodeDatas['tlive']) ? json_encode($decodeDatas['tlive']) : '',
            'match_id' => $decodeDatas['id'],
            'match_trend' => json_encode($match_trend_info),
            'is_stop' => ($statusId == 8) ? 1 : 0
        ];
        if (!$res = AdminMatchTlive::create()->where('match_id', $match_id)->get()) {
            AdminMatchTlive::create($match_tlive_data)->save();
        } else {
            unset($match_tlive_data['match_id']);
            AdminMatchTlive::create()->update($match_tlive_data, ['match_id' => $decodeDatas['id']]);
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
    }

    /**
     * ???????????? 1hour/???
     */
    public function updateSeason()
    {

        $max_updated_at = AdminSeason::create()->max('updated_at');
        $url = sprintf($this->season_url, $this->user, $this->secret, $max_updated_at + 1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return;
            }
            $decode = $resp['results'];
            if ($decode) {
                foreach ($decode as $item) {
                    $data = [
                        'season_id' => $item['id'],
                        'competition_id' => $item['competition_id'],
                        'year' => $item['year'],
                        'updated_at' => $item['updated_at'],
                        'start_time' => $item['start_time'],
                        'end_time' => $item['end_time'],
                        'competition_rule_id' => $item['competition_rule_id'],
                        'has_player_stats' => $item['has_player_stats'],
                        'has_team_stats' => $item['has_team_stats'],
                        'has_table' => $item['has_table'],
                        'is_current' => $item['is_current'],
                    ];
                    if (!$season = AdminSeason::create()->where('season_id', $item['id'])->get()) {
                        //??????????????????
                        AdminSeason::create($data)->save();
                        //?????????????????????
                        $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $item['id']));
                        $decode = json_decode($res, true);
                        $resultsMatch = !empty($decode['results']) ? $decode['results'] : [];
                        if ($resultsMatch) {
                            foreach ($resultsMatch as $data) {
                                if (SeasonMatchList::create()->get(['match_id' => $data['id']])) continue;
                                $home_team = AdminTeam::create()->where('team_id', $data['home_team_id'])->get();
                                $away_team = AdminTeam::create()->where('team_id', $data['away_team_id'])->get();
                                if (!$home_team || !$away_team) continue;
                                $insertData = [
                                    'match_id' => $data['id'],
                                    'competition_id' => $data['competition_id'],
                                    'home_team_id' => $data['home_team_id'],
                                    'away_team_id' => $data['away_team_id'],
                                    'match_time' => $data['match_time'],
                                    'neutral' => $data['neutral'],
                                    'note' => $data['note'],
                                    'season_id' => $data['season_id'],
                                    'home_scores' => json_encode($data['home_scores']),
                                    'away_scores' => json_encode($data['away_scores']),
                                    'home_position' => $data['home_position'],
                                    'away_position' => $data['away_position'],
                                    'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                                    'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                                    'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                                    'round' => isset($data['round']) ? json_encode($data['round']) : '',
                                    'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                                    'status_id' => $data['status_id'],
                                    'updated_at' => $data['updated_at'],
                                ];
                                SeasonMatchList::create($insertData)->save();
                            }

                        } else {
                            continue;
                        }

                    } else {
                        $season->update($data);
                    }

                }
            }
        }
    }

    /**
     * ??????????????????????????????
     * ????????????
     */
    public function updatePlayerStat()
    {
        $max = AdminPlayerStat::create()->max('updated_at');
        $url = sprintf($this->player_stat, $this->user, $this->secret, $max+1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
            }
            $decode = $resp['results'];

            if ($decode) {
                foreach ($decode as $item) {
                    $data = [
                        'player_id' => $item['id'],
                        'team_id' => $item['team_id'],
                        'birthday' => $item['birthday'],
                        'age' => $item['age'],
                        'weight' => $item['weight'],
                        'height' => $item['height'],
                        'nationality' => $item['nationality'],
                        'market_value' => $item['market_value'],
                        'market_value_currency' => $item['market_value_currency'],
                        'contract_until' => $item['contract_until'],
                        'position' => $item['position'],
                        'name_zh' => $item['name_zh'],
                        'short_name_zh' => $item['short_name_zh'],
                        'name_en' => $item['name_en'],
                        'short_name_en' => $item['short_name_en'],
                        'logo' => $item['logo'],
                        'country_id' => $item['country_id'],
                        'preferred_foot' => $item['preferred_foot'],
                        'updated_at' => $item['updated_at'],
                        'ability' => !isset($item['ability']) ? '' : json_encode($item['ability']),
                        'characteristics' => !isset($item['characteristics']) ? '' : json_encode($item['characteristics']),
                        'positions' => !isset($item['positions']) ? '' : json_encode($item['positions']),
                    ];
                    if (!$player = AdminPlayerStat::create()->where('player_id', $item['id'])->get()) {

                        AdminPlayerStat::create($data)->save();
                    } else {
                        AdminPlayerStat::create()->update($data, ['player_id' => $item['id']]);
                    }
                }
            }
        }
    }


    /**
     * ?????????????????? ????????????
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function playerChangeClubHistory()
    {
        $max = AdminPlayerChangeClub::create()->max('updated_at');
        $url = sprintf($this->player_change_club_history, $this->user, $this->secret, $max+1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);
            }
            $decode = $resp['results'];
            if ($decode) {
                foreach ($decode as $item) {
                    $data = [
                        'id' => $item['id'],
                        'player_id' => $item['player_id'],
                        'from_team_id' => $item['from_team_id'],
                        'from_team_name' => $item['from_team_name'],
                        'to_team_id' => $item['to_team_id'],
                        'to_team_name' => $item['to_team_name'],
                        'transfer_type' => $item['transfer_type'],
                        'transfer_time' => $item['transfer_time'],
                        'transfer_fee' => $item['transfer_fee'],
                        'transfer_desc' => $item['transfer_desc'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminPlayerChangeClub::create()->where('id', $item['id'])->get()) {
                        AdminPlayerChangeClub::create($data)->save();
                    } else {
                        //??????????????????
                        continue;
                    }
                }
            }
        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }
    }

    /**
     * ????????????  ????????????
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function teamHonor()
    {
        $max = AdminTeamHonor::create()->max('updated_at');
        $url = sprintf($this->team_honor, $this->user, $this->secret, $max + 1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                Log::getInstance()->info('????????????????????????');

            }
            $decode = $resp['results'];
            foreach ($decode as $item) {
                $data = [
                    'team_id' => $item['id'],
                    'honors' => json_encode($item['honors']),
                    'team' => json_encode($item['team']),
                    'update_at' => $item['updated_at']
                ];
                if (!AdminTeamHonor::create()->where('team_id', $item['id'])->get()) {
                    AdminTeamHonor::create($data)->save();
                } else {
                    $team_id = $data['team_id'];
                    unset($data['team_id']);
                    AdminTeamHonor::create()->update($data, ['team_id'=> $team_id]);
                }
            }
        } else {
            Log::getInstance()->info('??????????????????????????????');

        }

    }

    /**
     * ????????????  one day /time
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function honorList()
    {


        $max = AdminHonorList::create()->max('updated_at');
        $url = sprintf($this->honor_list, $this->user, $this->secret, $max + 1);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
            $decode = $resp['results'];
            foreach ($decode as $item) {
                $data = [
                    'id' => $item['id'],
                    'title_zh' => $item['title_zh'],
                    'logo' => $item['logo'],
                    'updated_at' => $item['updated_at']
                ];
                if (!AdminHonorList::create()->where('id', $item['id'])->get()) {
                    AdminHonorList::create($data)->save();
                } else {
                    $id = $data['id'];
                    unset($data['id']);
                    AdminHonorList::create()->update($data, ['id' => $id]);
                }
            }
        } else {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

        }

    }

    /**
     * ????????????  ???????????? ?????????????????????????????????????????????  ?????????????????????????????????
     * ????????????????????????????????????????????? ??????1/8???????????????????????????
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function stageList()
    {

        $max = AdminStageList::create()->max('updated_at');
        $url = sprintf($this->stage_list, $this->user, $this->secret, $max);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);
        $result = !empty($resp['results']) ? $resp['results'] : [];
        if ($result) {
            foreach ($result as $item) {
                $data = [
                    'stage_id' => $item['id'],
                    'season_id' => $item['season_id'],
                    'name_zh' => $item['name_zh'],
                    'name_zht' => $item['name_zht'],
                    'name_en' => $item['name_en'],
                    'mode' => $item['mode'],
                    'group_count' => $item['group_count'],
                    'round_count' => $item['round_count'],
                    'order' => $item['order'],
                    'updated_at' => $item['updated_at'],
                ];
                if (!AdminStageList::create()->where('stage_id', $item['id'])->get()) {
                    AdminStageList::create($data)->save();
                    //??????????????????
                    $select_season_id = $data['season_id'];
                    $res = Tool::getInstance()->postApi(sprintf('https://open.sportnanoapi.com/api/v4/football/match/season?user=%s&secret=%s&id=%s', 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $select_season_id));
                    $decode = json_decode($res, true);
                    $resultsMatch = !empty($decode['results']) ? $decode['results'] : [];
                    if ($resultsMatch) {
                        foreach ($resultsMatch as $data) {
                            if (SeasonMatchList::create()->get(['match_id' => $data['id']])) continue;
                            $insertData = [
                                'match_id' => $data['id'],
                                'competition_id' => $data['competition_id'],
                                'home_team_id' => $data['home_team_id'],
                                'away_team_id' => $data['away_team_id'],
                                'match_time' => $data['match_time'],
                                'neutral' => $data['neutral'],
                                'note' => $data['note'],
                                'season_id' => $data['season_id'],
                                'home_scores' => json_encode($data['home_scores']),
                                'away_scores' => json_encode($data['away_scores']),
                                'home_position' => $data['home_position'],
                                'away_position' => $data['away_position'],
                                'coverage' => isset($data['coverage']) ? json_encode($data['coverage']) : '',
                                'venue_id' => isset($data['venue_id']) ? $data['venue_id'] : 0,
                                'referee_id' => isset($data['referee_id']) ? $data['referee_id'] : 0,
                                'round' => isset($data['round']) ? json_encode($data['round']) : '',
                                'environment' => isset($data['environment']) ? json_encode($data['environment']) : '',
                                'status_id' => $data['status_id'],
                                'updated_at' => $data['updated_at'],
                            ];
                            SeasonMatchList::create($insertData)->save();
                        }

                    } else {
                        continue;
                    }

                } else {

                    AdminStageList::create()->update($data, ['stage_id'=>$item['id']]);
                }
            }
        } else {
            return;
        }

    }

    /**
     * ????????????  one day /time
     */
    public function managerList()
    {
        $manager_id = AdminManagerList::create()->max('updated_at');
        $url = sprintf($this->manager_list, $this->user, $this->secret, $manager_id);
        $res = Tool::getInstance()->postApi($url);
        $resp = json_decode($res, true);

        if ($resp['code'] == 0) {
            if ($resp['query']['total'] == 0) {
                return;
            }
            $decode = $resp['results'];
            foreach ($decode as $item) {

                $data = [
                    'manager_id' => $item['id'],
                    'team_id' => $item['team_id'],
                    'name_zh' => $item['name_zh'],
                    'name_en' => $item['name_en'],
                    'logo' => $item['logo'],
                    'age' => $item['age'],
                    'birthday' => $item['birthday'],
                    'preferred_formation' => $item['preferred_formation'],
                    'nationality' => $item['nationality'],
                    'updated_at' => $item['updated_at'],
                ];
                if (!AdminManagerList::create()->where('manager_id', $item['id'])->get()) {
                    AdminManagerList::create($data)->save();
                } else {
                    AdminManagerList::create()->update($data, ['manager_id' => $item['id']]);
                }
            }
        } else {
            return;
        }

    }


    /**
     * ?????????????????? ????????????
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function playerHonorList()
    {
        while (true){
            $max = AdminPlayerHonorList::create()->max('updated_at');

            $url = sprintf($this->player_honor_list, $this->user, $this->secret, $max + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);


                }
                $decode = $resp['results'];

                foreach ($decode as $item) {

                    $data = [
                        'player_id' => $item['id'],
                        'player' => json_encode($item['player']),
                        'honors' => json_encode($item['honors']),
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminPlayerHonorList::create()->where('player_id', $item['id'])->get()) {
                        AdminPlayerHonorList::create($data)->save();
                    } else {

                        AdminPlayerHonorList::create()->update($data, ['player_id' => $item['id']]);
                    }
                }
            } else {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        }
    }

    /**
     * ???????????? one day /time
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function competitionRule()
    {

        while (true){
            $start_id = AdminCompetitionRuleList::create()->max('updated_at');

            $url = sprintf($this->competition_rule, $this->user, $this->secret, $start_id + 1);
            $res = Tool::getInstance()->postApi($url);
            $resp = json_decode($res, true);

            if ($resp['code'] == 0) {
                if ($resp['query']['total'] == 0) {
                    return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

                }
                $decode = $resp['results'];
                foreach ($decode as $item) {

                    $data = [
                        'id' => $item['id'],
                        'competition_id' => $item['competition_id'],
                        'season_ids' => json_encode($item['season_ids']),
                        'text' => $item['text'],
                        'updated_at' => $item['updated_at'],
                    ];
                    if (!AdminCompetitionRuleList::create()->where('id', $item['id'])->get()) {
                        AdminCompetitionRuleList::create($data)->save();
                    } else {

                        AdminCompetitionRuleList::create()->update($data, ['id' => $item['id']]);
                    }
                }
            } else {
                return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK]);

            }
        }
    }





    /**
     * ?????????????????? 30???/???  ??????????????????
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function matchTlive()
    {
        Log::getInstance()->info('push match tlive start');
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, $this->user, $this->secret));
        $decode = json_decode($res, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return Log::getInstance()->info('match tlive json decode error');
        }
        if ($decode) {
            Log::getInstance()->info('accept data success');
            $match_info = [];
            foreach ($decode as $item) {
                $matchId = $item['id'];
                //???????????? ??????
                if (!$match = AdminMatch::create()->where('match_id', $matchId)->get()) {
                    continue;
                }

                //???????????? ??????
                $matchTliveRes = AdminMatchTlive::create()->where('match_id', $matchId)->get();
                if (isset($matchTliveRes->is_stop) && $matchTliveRes->is_stop == 1) continue;


                $status = $item['score'][1];
                Cache::set('football-match-status-' . $matchId, $status, 60 * 240);
                //?????????????????????
                $cacheExceptionMatchIds = [];
                if ($cacheExceptionMatch = Cache::get('exception-match')) {
                    $cacheExceptionMatchIds = json_decode($cacheExceptionMatch, true);
                }
                if (!in_array($status, [1, 2, 3, 4, 5, 7, 8])) { //????????? / ????????? / ?????? / ????????? / ???????????? / ??????
                    if (!in_array($matchId, $cacheExceptionMatchIds)) {
                        //??????
                        (new WebSocket())->noticeException($matchId, $status, 1);
                        array_push($cacheExceptionMatchIds, $matchId);
                        Cache::set('exception-match', json_encode($cacheExceptionMatchIds), 60 * 60);
                        $match->status_id = $status;
                        $match->update();
                        AppFunc::delMatchingInfo($matchId);
                    }
                    continue;
                }

                //??????????????????
                if ($item['score'][1] == 8) { //??????
                    TaskManager::getInstance()->async(new MatchNotice(['match_id' => $matchId,  'item' => $item,'score' => $item['score'],  'type'=>12]));
                }

                //?????????????????????  ??????
                if (!AppFunc::isInHotCompetition($match->competition_id)) {
                    continue;
                }
                $match_trend_info = [];
                if (isset($matchTliveRes->match_trend)) {
                    $match_trend_info = json_decode($matchTliveRes->match_trend, true);
                }
                //????????????????????????
                AppFunc::setPlayingTime($matchId, $item['score']);
                //?????????????????????
                if ($item['score'][1] == 2 && !Cache::get('match_notice_start:' . $item['id'])) { //??????
                    TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'score' => $item['score'],'item' => $item,  'type'=>10]));
                    Cache::set('match_notice_start:' . $item['id'], 1, 60 * 240);
                }
                $matchStats = [];
                if (!empty($item['stats'])) {
                    foreach ($item['stats'] as $ki => $vi) {
                        // 21????????? 22?????????  23:??????  24???????????? 25????????????
                        if ($vi['type'] == 21 || $vi['type'] == 22 || $vi['type'] == 23 || $vi['type'] == 24 || $vi['type'] == 25) {
                            $matchStats[] = $vi;
                        }
                    }
                    Cache::set('match_stats_' . $item['id'], json_encode($matchStats), 60 * 240);

                }
                $corner_count_tlive = $goal_incident = $yellow_card_incident = $red_card_incident = [];
                $corner_count_new = 0;
                $goal_count_new = 0;
                $yellow_card_count_new = 0;
                $red_card_count_new = 0;
                //???????????????????????????tlive??????
                if (!empty($item['tlive'])) {
                    Cache::set('match_tlive_' . $item['id'], json_encode($item['tlive']), 60 * 240);
                    $match_tlive_count_new = count($item['tlive']);
                    //????????????????????????
                    $match_tlive_count_old = Cache::get('match_tlive_count' . $item['id']) ?: 0;
                    if ($match_tlive_count_new > $match_tlive_count_old) { //????????????
                        Cache::set('match_tlive_count' . $item['id'], $match_tlive_count_new, 60 * 240);
                        $diff = array_slice($item['tlive'], $match_tlive_count_old);
                        (new WebSocket())->contentPush($diff, $item['id']);
                    }
                    foreach ($item['tlive'] as $itemTlive) {
                        if ($itemTlive['type'] == 2 && $itemTlive['main']) { //??????
                            $corner_count_new += 1;
                            $itemTlive['time'] = (int)trim($itemTlive['time'], "'");
                            $corner_count_tlive[] = $itemTlive;
                        }
                    }
                }
                if (!empty($item['incidents'])) {
                    //????????????????????????
                    $goal_count_old = Cache::get('goal_count_' . $item['id']);
                    //????????????????????????
                    $yellow_card_count_old = Cache::get('yellow_card_count' . $item['id']);
                    //????????????????????????
                    $red_card_count_old = Cache::get('red_card_count' . $item['id']);
                    foreach ($item['incidents'] as $itemIncident) {
                        if ($itemIncident['type'] == 1) { //??????
                            $goal_count_new += 1;
                            $last_goal_incident = $itemIncident;
                            $goal_incident[] = $itemIncident;
                        } else if ($itemIncident['type'] == 3) { //??????
                            $yellow_card_count_new += 1;
                            $last_yellow_card_incident = $itemIncident;
                            $yellow_card_incident[] = $itemIncident;
                        } else if ($itemIncident['type'] == 4) { //??????
                            $red_card_count_new += 1;
                            $last_red_card_incident = $itemIncident;
                            $red_card_incident[] = $itemIncident;
                        }
                    }

                    if ($goal_count_new > $goal_count_old && isset($last_goal_incident)) { //??????
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_goal_incident, 'score' => $item['score'], 'type'=>1]));
                        Cache::set('goal_count_' . $item['id'], $goal_count_new, 60 * 240);
                    }

                    if ($yellow_card_count_new > $yellow_card_count_old && isset($last_yellow_card_tlive)) { //??????
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_yellow_card_incident, 'score' => $item['score'], 'type'=>3]));
                        Cache::set('yellow_card_count' . $item['id'], $yellow_card_count_new, 60 * 240);
                    }

                    if ($red_card_count_new > $red_card_count_old && isset($last_red_card_tlive)) { //??????
                        TaskManager::getInstance()->async(new MatchNotice(['match_id' => $item['id'], 'last_incident' => $last_red_card_incident, 'score' => $item['score'], 'type'=>4]));
                        Cache::set('red_card_count' . $item['id'], $red_card_count_new, 60 * 240);
                    }
                }
                $signal_match_info['signal_count'] = ['corner' => $corner_count_tlive, 'goal' => $goal_incident, 'yellow_card' => $yellow_card_incident, 'red_card' => $red_card_incident];
                $signal_match_info['match_trend'] = $match_trend_info;
                $signal_match_info['match_id'] = $item['id'];
                $signal_match_info['time'] = AppFunc::getPlayingTime($item['id']);
                $signal_match_info['status_id'] = $status;
                $signal_match_info['match_stats'] = $matchStats;
                $signal_match_info['user_num'] = count(AppFunc::getUsersInRoom($matchId, 1));
                $signal_match_info['score'] = [
                    'home' => $item['score'][2],
                    'away' => $item['score'][3]
                ];
                list($signal_match_info['home_total_scores'], $signal_match_info['away_total_scores']) = AppFunc::getFinalScore($item['score'][2], $item['score'][3]);
                $match_info[] = $signal_match_info;
                AppFunc::setMatchingInfo($item['id'], json_encode($signal_match_info));
                unset($signal_match_info);
            }

            Log::getInstance()->info('start push matching_info_list');

            /**
             * ?????????????????????????????????????????????????????????????????????????????????push???????????????????????????????????????
             */

            if (!empty($match_info)) {
                $tool = Tool::getInstance();
                $server = ServerManager::getInstance()->getSwooleServer();
                $returnData = [
                    'event' => 'match_update',
                    'match_info_list' => $match_info
                ];

                $onlineUsers = OnlineUser::getInstance()->table();
                foreach ($onlineUsers as $fd => $onlineUser) {
                    $connection = $server->connection_info($fd);
                    if (is_array($connection) && $connection['websocket_status'] == 3) {  // ?????????????????????????????????????????????
                        Log::getInstance()->info('push succ' . $fd);
                        $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
                    } else {
                        Log::getInstance()->info('lost-connection-' . $fd);
                    }
                }

            } else {
                Log::getInstance()->info('do not have match to hand');

            }

        } else {
            Log::getInstance()->info('accept data failed');

        }
    }
    /**
     * ????????????????????????????????????
     * @throws \Throwable
     */
    public function updateMatchTrend()
    {
        //??????????????????
        if ($playingMatches = AdminMatch::create()->field(['match_id'])->where('status_id', FootballApi::STATUS_PLAYING, 'in')->all()) {
            foreach ($playingMatches as $playingMatch) {
                //????????????
                $match_res = Tool::getInstance()->postApi(sprintf($this->trend_detail, 'mark9527', 'dbfe8d40baa7374d54596ea513d8da96', $playingMatch['match_id']));
                $match_trend = json_decode($match_res, true);

                if ($match_trend['code'] != 0) {
                    $match_trend_info = [];
                } else {
                    $match_trend_info = $match_trend['results'];
                }

                if ($matchTlive = AdminMatchTlive::create()->where('match_id', $playingMatch['match_id'])->get()) {
                    $matchTlive->match_trend = json_encode($match_trend_info);
                    $matchTlive->update();
                } else {
                    $insertData = [
                        'match_id' => $playingMatch['match_id'],
                        'match_trend' => json_encode($match_trend_info)
                    ];
                    AdminMatchTlive::create($insertData)->save();
                }
            }

        }

    }



    public function testWorking()
    {
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, $this->user, $this->secret));
        $decode = json_decode($res, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return Log::getInstance()->info('match tlive json decode error');
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decode);

    }

    public function testMysql()
    {
        $res = Tool::getInstance()->postApi(sprintf($this->live_url, $this->user, $this->secret));
        $decode = json_decode($res, true);
        if (json_last_error() != JSON_ERROR_NONE) {
            return Log::getInstance()->info('match tlive json decode error');
        }
        if ($decode) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], $decode);

        }
    }

    /**
     * ????????????????????????????????????
     */
    public function getUserInSeasons()
    {
        //??????????????????????????????????????????
        $seasons = AdminSeason::create()
            ->where('has_player_stats', 1)->where('is_current', 1)->order('season_id', 'ASC')->all();
        if (!$seasons) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
        }
        foreach ($seasons as $season) {
            $selectSeasonId = $season->season_id;
            Cache::set('update-season-player-stats-seasonid', $selectSeasonId, 60 * 60);
            $seasonPlayerStats = SeasonTeamPlayer::create()->field(['season_id', 'players_stats'])->where('season_id', $selectSeasonId)->get();
            if (!$seasonPlayerStats) continue;
            $playerStats = json_decode($seasonPlayerStats->players_stats, true);
            foreach ($playerStats as $playerStat) {
                $playerId = $playerStat['player']['id'];
                $playerSeason = AdminPlayer::create()->where('player_id', $playerId)->get();
                if (!$playerSeason) continue;
                $formatPlayerSeason = json_decode($playerSeason->seasons, true);
                if (!$formatPlayerSeason) {
                    $playerSeason->seasons = json_encode([$selectSeasonId]);
                } else {
                    if (!in_array($season->season_id, $formatPlayerSeason)) {
                        array_push($formatPlayerSeason, $selectSeasonId);
                        $playerSeason->seasons = json_encode($formatPlayerSeason);
                    }
                }

                $playerSeason->update();

            }
        }


    }

    /**
     * ????????????????????????????????????
     */
    public function getTeamsInSeasons()
    {
        //??????????????????????????????????????????
        $seasons = AdminSeason::create()
            ->where('has_team_stats', 1)->order('season_id', 'ASC')->where('season_id', 10088, '>')->limit(1000)->all();
        if (!$seasons) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
        }
        foreach ($seasons as $season) {
            $selectSeasonId = $season->season_id;
            Cache::set('update-season-team-stats-seasonid', $selectSeasonId, 60 * 60);
            $seasonPlayerStats = SeasonTeamPlayer::create()->field(['season_id', 'teams_stats'])->where('season_id', $selectSeasonId)->get();
            if (!$seasonPlayerStats || !$teamsStats = json_decode($seasonPlayerStats->teams_stats, true)) continue;
            foreach ($teamsStats as $playerStat) {
                $playerId = $playerStat['team']['id'];
                $team = AdminTeam::create()->where('team_id', $playerId)->get();
                if (!$team) continue;
                $formatPlayerSeason = json_decode($team->seasons, true);
                if (!$formatPlayerSeason) {
                    $team->seasons = json_encode([$selectSeasonId]);
                } else {
                    if (!in_array($season->season_id, $formatPlayerSeason)) {
                        array_push($formatPlayerSeason, $selectSeasonId);
                        $team->seasons = json_encode($formatPlayerSeason);
                    }
                }

                $team->update();

            }
        }
        var_dump('123');



    }

}
