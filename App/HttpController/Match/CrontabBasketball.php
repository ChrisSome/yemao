<?php
namespace App\HttpController\Match;

use App\Base\FrontUserController;
use App\Common\AppFunc;
use App\HttpController\User\WebSocket;
use App\lib\FrontService;
use App\lib\Tool;
use App\Model\BasketballCategory;
use App\Model\BasketBallCompetition;
use App\Model\BasketballHonor;
use App\Model\BasketballLineUp;
use App\Model\BasketballMatch;
use App\Model\BasketballMatchSeason;
use App\Model\BasketballMatchTlive;
use App\Model\BasketballPlayer;
use App\Model\BasketballPlayerHonor;
use App\Model\BasketballSeasonAllStatsDetail;
use App\Model\BasketballSeasonList;
use App\Model\BasketballSeasonTable;
use App\Model\BasketballSquadList;
use App\Model\BasketballTeam;
use App\Model\BasketballTeamHonorList;
use App\Storage\OnlineUser;
use App\Task\BasketballMatchNotice;
use App\Utility\Log\Log;
use App\Utility\Message\Status;
use App\Utility\Message\Status as Statuses;
use App\WebSocket\WebSocketStatus;
use easySwoole\Cache\Cache;
use EasySwoole\EasySwoole\ServerManager;
use EasySwoole\EasySwoole\Task\TaskManager;
use EasySwoole\Mysqli\QueryBuilder;
use EasySwoole\ORM\DbManager;

class CrontabBasketball extends FrontUserController
{
    protected $user = 'mark9527';
    protected $secret = 'dbfe8d40baa7374d54596ea513d8da96';
    protected $url = 'https://open.sportnanoapi.com';

    protected $categoryList = 'https://open.sportnanoapi.com/api/v4/basketball/category/list?user=%s&secret=%s';//获取分类列表
    protected $competitionList = 'https://open.sportnanoapi.com/api/v4/basketball/competition/list?user=%s&secret=%s&time=%s';//获取赛事列表
    protected $teamList = 'https://open.sportnanoapi.com/api/v4/basketball/team/list?user=%s&secret=%s&time=%s';//获取球队列表
    protected $lineUp = 'https://open.sportnanoapi.com/api/v4/basketball/team/squad/list?user=%s&secret=%s&time=%s';//获取阵容列表
    protected $playerList = 'https://open.sportnanoapi.com/api/v4/basketball/player/list?user=%s&secret=%s&time=%s';//获取球员列表
    protected $playerHonor = 'https://open.sportnanoapi.com/api/v4/basketball/player/honor/list?user=%s&secret=%s&time=%s';//获取球员列表
    protected $matchDiary = 'https://open.sportnanoapi.com/api/v4/basketball/match/diary?user=%s&secret=%s&date=%s';//获取比赛列表
    protected $honorList = 'https://open.sportnanoapi.com/api/v4/basketball/honor/list?user=%s&secret=%s&time=%s';//获取比赛列表
    protected $seasonList = 'https://open.sportnanoapi.com/api/v4/basketball/season/list?user=%s&secret=%s&time=%s';//获取赛季列表
    protected $seasonAllStatsDetail = 'https://open.sportnanoapi.com/api/v4/basketball/season/all/stats/detail?user=%s&secret=%s&id=%s';//获取赛季球队球员统计详情-全量
    protected $matchSeason = 'https://open.sportnanoapi.com/api/v4/basketball/match/season?user=%s&secret=%s&id=%s';//获取比赛
    protected $seasonTable = 'https://open.sportnanoapi.com/api/v4/basketball/season/all/table/detail?user=%s&secret=%s&id=%s';//获取赛季积分榜数据-全量
    protected $squadList = 'https://open.sportnanoapi.com/api/v4/basketball/team/squad/list?user=%s&secret=%s&time=%s';//获取球队阵容列表
    protected $matchTlive = 'https://open.sportnanoapi.com/api/sports/basketball/match/detail_live?user=%s&secret=%s';//获取篮球直播
    protected $matchTrend = 'https://open.sportnanoapi.com/api/v4/basketball/match/trend/detail?user=%s&secret=%s&id=%s';//获取篮球比赛趋势
    protected $matchHistory = 'https://open.sportnanoapi.com/api/v4/basketball/match/live/history?user=%s&secret=%s&id=%s';//获取历史比赛统计数据
    protected $changedMatchList = 'https://open.sportnanoapi.com/api/v4/basketball/match/list?user=%s&secret=%s&time=%s';//获取变动比赛
    protected $deleteMatch = 'https://open.sportnanoapi.com/api/v4/basketball/deleted?user=%s&secret=%s';//删除的比赛
    protected $teamHonroList = 'https://open.sportnanoapi.com/api/v4/basketball/team/honor/list?user=%s&secret=%s&id=%s';//球队荣誉


    /**
     * 获取分类列表，一天一次
     */
    public function getBasketBallCategoryList()
    {
        $url = sprintf($this->categoryList, $this->user, $this->secret);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];
        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球分类更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {
                $item = [
                    'name_zh' => $data['name_zh'],
                    'name_zht' => $data['name_zht'],
                    'updated_at' => $data['updated_at'],
                    'name_en' => $data['name_en'],
                ];
                if ($res = BasketballCategory::create()->where('category_id', $data['id'])->get()) {
                    $res->name_zh = $data['name_zh'];
                    $res->name_zht = $data['name_zht'];
                    $res->updated_at = $data['updated_at'];
                    $res->name_en = $data['name_en'];
                    $res->update();
                } else {
                    $item['category_id'] = $data['id'];
                    BasketballCategory::create($item)->save();
                }

            }
            return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], 1);
        }
    }


    /**
     * 篮球赛事更新接口  每小时一次
     * @return bool|void
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getBasketBallCompetitionList()
    {
        $maxUpdated = BasketBallCompetition::create()->max('updated_at') + 1;
        $url = sprintf($this->competitionList, $this->user, $this->secret, $maxUpdated);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            return $this->writeJson(Statuses::CODE_OK, 'no data');
        } else {
            foreach ($decodeDatas as $data) {
                if ($basCompetition = BasketBallCompetition::create()->where('competition_id', $data['id'])->get()) {
                    $basCompetition->category_id = $data['category_id'];
                    $basCompetition->country_id = $data['country_id'];
                    $basCompetition->name_zh = $data['name_zh'];
                    $basCompetition->short_name_zh = $data['short_name_zh'];
                    $basCompetition->name_zht = $data['name_zht'];
                    $basCompetition->short_name_zht = $data['short_name_zht'];
                    $basCompetition->name_en = $data['name_en'];
                    $basCompetition->short_name_en = $data['short_name_en'];
                    $basCompetition->updated_at = $data['updated_at'];
                    $basCompetition->logo = $data['logo'];
                    $basCompetition->update();
                } else {
                    $insert = [
                        'competition_id' => $data['id'],
                        'category_id' => $data['category_id'],
                        'country_id' => $data['country_id'],
                        'name_zh' => $data['name_zh'],
                        'short_name_zh' => $data['short_name_zh'],
                        'name_zht' => $data['name_zht'],
                        'short_name_zht' => $data['short_name_zht'],
                        'name_en' => $data['short_name_en'],
                        'logo' => $data['logo'],
                        'updated_at' => $data['updated_at'],
                        'short_name_en' => $data['short_name_en'],
                    ];
                    BasketBallCompetition::create($insert)->save();
                }

            }
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

        }
    }

    /**
     * 球队 1天/次
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getBasketballTeamList()
    {
        $maxUpdated = BasketballTeam::create()->max('updated_at') + 1;

        $url = sprintf($this->teamList, $this->user, $this->secret, $maxUpdated);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球赛事更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {
                if ($team = BasketballTeam::create()->where('team_id', $data['id'])->get()) {
                    $team->team_id = $data['id'];
                    $team->competition_id = $data['competition_id'];
                    $team->conference_id = $data['conference_id'];
                    $team->name_zh = $data['name_zh'];
                    $team->short_name_zh = $data['short_name_zh'];
                    $team->name_zht = $data['name_zht'];
                    $team->short_name_zht = $data['short_name_zht'];
                    $team->name_en = $data['name_en'];
                    $team->short_name_en = $data['short_name_en'];
                    $team->logo = $data['logo'];
                    $team->updated_at = $data['updated_at'];
                    $team->update();
                } else {

                    $insert = [
                        'team_id' => $data['id'],
                        'competition_id' => $data['competition_id'],
                        'conference_id' => $data['conference_id'],
                        'name_zh' => $data['name_zh'],
                        'short_name_zh' => $data['short_name_zh'],
                        'name_zht' => $data['name_zht'],
                        'short_name_zht' => $data['short_name_zht'],
                        'short_name_en' => $data['conference_id'],
                        'name_en' => $data['name_en'],
                        'logo' => $data['logo'],
                        'updated_at' => $data['updated_at'],
                    ];
                    BasketballTeam::create($insert)->save();
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 球队阵容 1天/次
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getLineUpList()
    {
        $maxUpdated = (int)BasketballLineUp::create()->max('updated_at') + 1;
        $url = sprintf($this->lineUp, $this->user, $this->secret, $maxUpdated);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球阵容更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {
                if ($team = BasketballLineUp::create()->where('team_id', $data['id'])->get()) {
                    $team->team = json_encode($data['team']);
                    $team->squad = json_encode($data['squad']);
                    $team->updated_at = (int)$data['updated_at'];
                    $team->update();
                } else {
                    $insert = [
                        'team_id' => (int)$data['id'],
                        'team' => json_encode($data['team']),
                        'squad' => json_encode($data['squad']),
                        'updated_at' => (int)$data['updated_at'],

                    ];
                    BasketballLineUp::create($insert)->save();
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 球员列表
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getPlayerList()
    {
        $maxUpdated = (int)BasketballPlayer::create()->max('updated_at') + 1;
        $url = sprintf($this->playerList, $this->user, $this->secret, $maxUpdated);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];
        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球球员更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {

                if ($player = BasketballPlayer::create()->where('player_id', $data['id'])->get()) {
                    $player->name_zh = $data['name_zh'];
                    $player->short_name_zh = $data['short_name_zh'];
                    $player->name_en = $data['name_en'];
                    $player->short_name_en = $data['short_name_en'];
                    $player->team_id = $data['team_id'];
                    $player->logo = $data['logo'];
                    $player->age = $data['age'];
                    $player->birthday = $data['birthday'];
                    $player->weight = $data['weight'];
                    $player->height = $data['height'];
                    $player->drafted = $data['drafted'];
                    $player->league_career_age = $data['league_career_age'];
                    $player->school = $data['school'];
                    $player->city = $data['city'];
                    $player->shirt_number = $data['shirt_number'];
                    $player->position = $data['position'];
                    $player->updated_at = $data['updated_at'];

                    $player->update();
                } else {

                    $insert = [
                        'player_id' => (int)$data['id'],
                        'name_zh' => $data['name_zh'],
                        'short_name_zh' => $data['short_name_zh'],
                        'short_name_en' => $data['short_name_en'],
                        'name_en' => $data['name_en'],
                        'team_id' => $data['team_id'],
                        'logo' => $data['logo'],
                        'age' => $data['age'],
                        'birthday' => $data['birthday'],
                        'weight' => $data['weight'],
                        'height' => $data['height'],
                        'drafted' => $data['drafted'],
                        'league_career_age' => $data['league_career_age'],
                        'school' => $data['school'],
                        'city' => $data['city'],
                        'salary' => $data['salary'],
                        'shirt_number' => $data['shirt_number'],
                        'position' => $data['position'],
                        'updated_at' => $data['updated_at'],

                    ];
                    BasketballPlayer::create($insert)->save();
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 球员荣誉表
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getPlayerHonor()
    {
        $maxUpdated = (int)BasketballPlayerHonor::create()->max('updated_at') + 1;

        $url = sprintf($this->playerHonor, $this->user, $this->secret, $maxUpdated);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球球员更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {

                if ($player = BasketballPlayerHonor::create()->where('player_id', $data['id'])->get()) {
                    $player->player = json_encode($data['name_zh']);
                    $player->honors = json_encode($data['honors']);
                    $player->updated_at = $data['updated_at'];
                    $player->update();
                } else {

                    $insert = [
                        'player_id' => (int)$data['id'],
                        'honors' => json_encode($data['honors']),
                        'player' => json_encode($data['player']),
                        'updated_at' => $data['updated_at'],

                    ];
                    BasketballPlayerHonor::create($insert)->save();
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 比赛列表  10分钟一次
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getMatchListDiary1($date = '')
    {
        if (!$date) {
            $date = date('Ymd');
        }
        $url = sprintf($this->matchDiary, $this->user, $this->secret, $date);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];
        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球球员更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');
        }
        $homeTeamIds = array_column($decodeDatas, 'home_team_id');
        $awayTeamIds = array_column($decodeDatas, 'away_team_id');
        $teamIds = array_unique(array_merge($homeTeamIds, $awayTeamIds));
        $teams = BasketballTeam::create()->where('team_id', $teamIds, 'in')->all();
        $sortTeams = $sortCompetition = [];
        array_walk($teams, function($v, $k) use(&$sortTeams) {
            $sortTeams[$v['team_id']] = $v;
        });
        $competitionsIds = array_column($decodeDatas, 'competition_id');
        $competitionArr = BasketBallCompetition::create()->where('competition_id', $competitionsIds, 'in')->all();
        array_walk($competitionArr, function($cv, $ck) use(&$sortCompetition) {
            $sortCompetition[$cv['competition_id']] = $cv;
        });

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球比赛更新无数据');
        } else {
            foreach ($decodeDatas as $data) {
                if (!isset($sortTeams[$data['home_team_id']]) || !isset($sortTeams[$data['away_team_id']])) continue;
                $home_team = $sortTeams[$data['home_team_id']];
                $away_team = $sortTeams[$data['away_team_id']];
                $competition = isset($sortCompetition[$data['competition_id']]) ? $sortCompetition[$data['competition_id']] : [];

                if ($match = BasketballMatch::create()->where('match_id', $data['id'])->get()) {
                    $match->status_id = (int)$data['status_id'];
                    $match->match_time = (int)$data['match_time'];
                    $match->note = $data['note'];
                    $match->neutral = $data['neutral'];
                    $match->home_scores = json_encode($data['home_scores']);
                    $match->away_scores = json_encode($data['away_scores']);
                    $match->coverage = json_encode($data['coverage']);
                    $match->home_team_name = isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh;
                    $match->home_team_logo = isset($home_team->logo) ? $home_team->logo : '';
                    $match->away_team_name = isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh;
                    $match->away_team_logo = isset($away_team->logo) ? $away_team->logo : '';
                    $match->update();
                } else {
                    $insert = [
                        'match_id' => (int)$data['id'],
                        'competition_id' => $data['competition_id'],
                        'home_team_id' => $data['home_team_id'],
                        'away_team_id' => $data['away_team_id'],
                        'status_id' => $data['status_id'],
                        'match_time' => $data['match_time'],
                        'neutral' => $data['neutral'],
                        'note' => $data['note'],
                        'home_scores' => json_encode($data['home_scores']),
                        'away_scores' => json_encode($data['away_scores']),
                        'period_count' => $data['period_count'],
                        'kind' => $data['kind'],
                        'coverage' => json_encode($data['coverage']),
                        'season_id' => isset($data['season_id']) ? $data['season_id'] : 0,
                        'round' => isset($data['round']) ? json_encode($data['round']) : '',
                        'venue_id' => (isset($data['venue_id'])) ? (int)$data['venue_id'] : 0,
                        'position' => isset($data['position']) ? json_encode($data['position']) : '',
                        'updated_at' => $data['updated_at'],
                        'home_team_name' => isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh,
                        'home_team_logo' => isset($home_team->logo) ? $home_team->logo : '',
                        'away_team_name' => isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh,
                        'away_team_logo' => isset($away_team->logo) ? $away_team->logo : '',
                        'competition_name' => isset($competition->short_name_zh) ? $competition->short_name_zh : '',
                    ];
                    BasketballMatch::create($insert)->save();
                }

                if ($seasonMatch = BasketballMatchSeason::create()->where('match_id', $data['id'])->get()) {
                    $seasonMatch->status_id = (int)$data['status_id'];
                    $seasonMatch->match_time = (int)$data['match_time'];
                    $seasonMatch->note = $data['note'];
                    $seasonMatch->neutral = $data['neutral'];
                    $seasonMatch->home_scores = json_encode($data['home_scores']);
                    $seasonMatch->away_scores = json_encode($data['away_scores']);
                    $seasonMatch->coverage = json_encode($data['coverage']);
                    $seasonMatch->home_team_name = isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh;
                    $seasonMatch->home_team_logo = isset($home_team->logo) ? $home_team->logo : '';
                    $seasonMatch->away_team_name = isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh;
                    $seasonMatch->away_team_logo = isset($away_team->logo) ? $away_team->logo : '';
                    $seasonMatch->update();
                } else {
                    if (isset($insert)) BasketballMatchSeason::create($insert)->save();
                }
            }
        }

        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 未来一周比赛
     */
    public function getMatchesForWeek()
    {
        $weeks = FrontService::getWeek();
        foreach ($weeks as $week) {
            $this->getMatchListDiary($week);
        }

    }
    /**
     * 荣誉列表
     * @return bool
     * @throws \EasySwoole\Mysqli\Exception\Exception
     * @throws \EasySwoole\ORM\Exception\Exception
     * @throws \Throwable
     */
    public function getHonorList()
    {
        $maxUpdated = (int)BasketballHonor::create()->max('updated_at') + 1;
        $url = sprintf($this->honorList, $this->user, $this->secret, $maxUpdated);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-m-d H:i:s') . '篮球荣誉更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');
        } else {
            foreach ($decodeDatas as $data) {

                if ($honor = BasketballHonor::create()->where('honor_id', $data['id'])->get()) {
                    $honor->title_zh = $data['title_zh'];
                    $honor->updated_at = $data['updated_at'];
                    $honor->logo = $data['logo'];
                    $honor->update();
                } else {
                    $insert = [
                        'honor_id' => (int)$data['id'],
                        'title_zh' => $data['title_zh'],
                        'updated_at' => $data['updated_at'],
                        'logo' => $data['logo'],
                    ];
                    BasketballHonor::create($insert)->save();
                }
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    /**
     * 赛季列表
     * @return bool
     * @throws \Throwable
     */
    public function getSeasonList()
    {
        $maxUpdated = (int)BasketballSeasonList::create()->max('updated_at') + 1;
        $url = sprintf($this->seasonList, $this->user, $this->secret, $maxUpdated);

        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);

        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球赛季更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');

        } else {
            foreach ($decodeDatas as $data) {
                if ($season = BasketballSeasonList::create()->where('season_id', $data['id'])->get()) {
                    $season->updated_at = $data['updated_at'];
                    $season->competition_id = $data['competition_id'];
                    $season->year = $data['year'];
                    $season->has_player_stats = $data['has_player_stats'];
                    $season->has_team_stats = $data['has_team_stats'];
                    $season->is_current = $data['is_current'];
                    $season->update();
                } else {
                    $insert = [
                        'updated_at' => $data['updated_at'],
                        'competition_id' => $data['competition_id'],
                        'year' => $data['year'],
                        'has_player_stats' => $data['has_player_stats'],
                        'has_team_stats' => $data['has_team_stats'],
                        'is_current' => $data['is_current'],
                        'season_id' => $data['id'],
                    ];
                    BasketballSeasonList::create($insert)->save();
                }
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    //获取赛季球队球员统计详情-全量
    public function getSeasonAllStatsDetail()
    {

        $seasonList = BasketballSeasonList::create()->field(['season_id'])->where('has_player_stats=1 or has_team_stats=1')->where('is_current', 1)->all();
        foreach ($seasonList as $item) {
            $url = sprintf($this->seasonAllStatsDetail, $this->user, $this->secret, $item['season_id']);
            $res = Tool::getInstance()->postApi($url);
            $teams = json_decode($res, true);

            $decodeDatas = $teams['results'];
            $data['team_stats'] = json_encode($decodeDatas['team_stats']);
            $data['player_stats'] = json_encode($decodeDatas['player_stats']);
            $data['season_id'] = $item['season_id'];
            if ($res = BasketballSeasonAllStatsDetail::create()->where('season_id', $item['season_id'])->get()) {
                $res->team_stats = json_encode($decodeDatas['team_stats']);
                $res->player_stats = json_encode($decodeDatas['player_stats']);
                $res->update();
            } else {
                BasketballSeasonAllStatsDetail::create($data)->save();
            }

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }
    //赛季比赛列表
    public function seasonMatch()
    {
        $seasonList = BasketballSeasonList::create()->where('is_current', 1)->all();
        //需要获取每个赛事最新的赛季id
        foreach ($seasonList as $item) {
            $url = sprintf($this->matchSeason, $this->user, $this->secret, $item['season_id']);
            $res = Tool::getInstance()->postApi($url);
            $decodeDatas = json_decode($res, true);
            if (!$results = $decodeDatas['results']) {
                continue;
            }
            foreach ($results as $data) {
                if ($match = BasketballMatchSeason::create()->where('match_id', $data['id'])->get()) {
                    $match->status_id = (int)$data['status_id'];
                    $match->match_time = (int)$data['match_time'];
                    $match->note = $data['note'];
                    $match->neutral = $data['neutral'];
                    $match->home_scores = json_encode($data['home_scores']);
                    $match->away_scores = json_encode($data['away_scores']);
                    $match->coverage = json_encode($data['coverage']);
                    $match->update();
                } else {
                    $insert = [
                        'match_id' => (int)$data['id'],
                        'competition_id' => $data['competition_id'],
                        'home_team_id' => $data['home_team_id'],
                        'away_team_id' => $data['away_team_id'],
                        'status_id' => $data['status_id'],
                        'match_time' => $data['match_time'],
                        'neutral' => $data['neutral'],
                        'note' => $data['note'],
                        'home_scores' => json_encode($data['home_scores']),
                        'away_scores' => json_encode($data['away_scores']),
                        'period_count' => $data['period_count'],
                        'kind' => $data['kind'],
                        'coverage' => json_encode($data['coverage']),
                        'season_id' => isset($data['season_id']) ? $data['season_id'] : 0,
                        'round' => isset($data['round']) ? json_encode($data['round']) : '',
                        'venue_id' => (isset($data['venue_id'])) ? (int)$data['venue_id'] : 0,
                        'position' => isset($data['position']) ? json_encode($data['position']) : '',
                        'updated_at' => $data['updated_at'],
                    ];
                    BasketballMatchSeason::create($insert)->save();
                }
            }

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }


    //赛季积分榜数据 ,每天一次
    public function seasonTable()
    {
        $seasonList = BasketballSeasonList::create()->where('is_current', 1)->all();
        foreach ($seasonList as $item) {
            $url = sprintf($this->seasonTable, $this->user, $this->secret, $item['season_id']);
            $res = Tool::getInstance()->postApi($url);
            $decodeDatas = json_decode($res, true);
            if (!$results = $decodeDatas['results']) {
                continue;
            }
            $selectSeason[] = $item['season_id'];
            if ($tableSeason = BasketballSeasonTable::create()->where('season_id', $item['season_id'])->get()) {
                $tableSeason->tables = json_encode($results['tables']);
                $tableSeason->update();
            } else {
                $insert['tables'] = json_encode($results['tables']);
                $insert['season_id'] = $item['season_id'];
                BasketballSeasonTable::create($insert)->save();
            }

        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);

    }

    public function squadList()
    {

        $maxUpdatedId = BasketballSquadList::create()->max('updated_at') + 1;
        $url = sprintf($this->squadList, $this->user, $this->secret, $maxUpdatedId);
        $res = Tool::getInstance()->postApi($url);
        $decodeDatas = json_decode($res, true);
        $results = $decodeDatas['results'];

        if (!$results) return $this->writeJson(Statuses::CODE_OK, 'no data');
        foreach ($results as $item) {
            if ($squad = BasketballSquadList::create()->where('team_id', $item['id'])->get()) {
                $squad->squad = json_encode($item['squad']);
                $squad->team = json_encode($item['team']);
                $squad->updated_at = $item['updated_at'];
                $squad->update();
            } else {
                $insert['team_id'] = $item['id'];
                $insert['squad'] = json_encode($item['squad']);
                $insert['team'] = json_encode($item['team']);
                $insert['updated_at'] = $item['updated_at'];
                BasketballSquadList::create($insert)->save();
            }
        }
    }

    /**
     * 篮球直播 20s/次
     */
    public function basketballMatchTlive()
    {

        $url = sprintf($this->matchTlive, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $decodeDatas = json_decode($res, true);
        if (!$decodeDatas || json_last_error()) {
            return Log::getInstance()->info('json decode error');
        }
        $matchingInfoList = [];
        foreach ($decodeDatas as $matchItem) {

            /**
             * 字段解释
             * score [2783605, 8, 0, [0, 0, 0, 0, 0], [0, 0, 0, 0, 0]] 比赛id，比赛状态，小节剩余秒数 ，主队分数， 客队分数
             * stats 当前小结技术统计字段，可能不存在 [1, 8, 9]  统计类型 主队值，客队值
             * tlive 文字直播字段 可能不存在
             * players 阵容字段 可能不存在
             */
            //无效比赛 跳过

            if (!$match = BasketballMatch::create()->where('match_id', $matchItem['id'])->get()) {
                continue;
            }
            if (BasketballMatchTlive::create()->where('match_id', $matchItem['id'])->where('is_stop', 1)->get()) {
                continue;
            }
            //| 状态码 |    描述
            //| ------ | ------------------------------------------------------------------------------
            //| 0      | 比赛异常，说明：暂未判断具体原因的异常比赛，可能但不限于：腰斩、取消等等，建议隐藏处理
            //| 1      | 未开赛
            //| 2      | 第一节
            //| 3      | 第一节完
            //| 4      | 第二节
            //| 5      | 第二节完
            //| 6      | 第三节
            //| 7      | 第三节完
            //| 8      | 第四节
            //| 9      | 加时
            //| 10     | 完场
            //| 11     | 中断
            //| 12     | 取消
            //| 13     | 延期
            //| 14     | 腰斩
            //| 15     | 待定
            $statusId = isset($matchItem['score'][1]) ? $matchItem['score'][1] : 0;
            if (!$statusId) continue;
            $matchId = $matchItem['id'];
            if (!in_array($statusId, [2,3,4,5,6,7,8,9,10])) {
                continue;
            }
            if (!empty($matchItem['players'])) {
                Cache::set('basketball-match-players-' . $matchId, json_encode($matchItem['players']), 60 * 60 * 3);
            }
            if ($statusId == 10) {//结束
                //发送结束通知
                TaskManager::getInstance()->async(new BasketballMatchNotice(['match_id' => $matchId,  'item' => $matchItem, 'matchModel' => $match,  'type'=>1]));
            }
            //不在热门中，跳过
            if (!AppFunc::isInHotBasketballCompetition($match->competition_id)) {
                continue;
            }
            if (isset($matchItem['score'])) {
                Cache::set('basketball-match-score-' . $matchId, json_encode($matchItem['score']), 60 * 60);
            }
            if (!empty($matchItem['stats'])) {
                Cache::set('basketball-match-stats-' . $matchId, json_encode($matchItem['stats']), 60 * 60);
            }
            if ($statusId == 2 && !Cache::get('basketball-start-' . $matchId)) {
                Log::getInstance()->info('bas-match-over');
                Cache::set('basketball-start-' . $matchId, 1, 60 * 60);
                TaskManager::getInstance()->async(new BasketballMatchNotice(['match_id' => $matchId,  'item' => $matchItem, 'matchModel' => $match,  'type'=>2]));

            }
            $matchTrendInfo = [];
            if ($matchTrendRes = BasketballMatchTlive::create()->where('match_id', $matchId)->get()) {
                $matchTrendInfo = json_decode($matchTrendRes->match_trend, true);
            }
            //设置比赛进行时间 小节剩余时间(秒)
            Cache::set('left-seconds-in-current-matter-' . $matchId, $matchItem['score'][2], 60 * 60);
            //进球数据
            $homeNewTotalScore = $awayNewTotalScore = 0;
            $homeScores = $matchItem['score'][3];
            $awayScores = $matchItem['score'][4];
            for ($i = 0; $i <= 4; $i++) {
                if (isset($homeScores[$i]) && isset($awayScores[$i])) {
                    $homeNewTotalScore += $homeScores[$i];
                    $awayNewTotalScore += $awayScores[$i];
                }
            }
            $matchingInfo = [
                'stats' => isset($matchItem['stats']) ? $matchItem['stats'] : [],
                'status_id' => $statusId,
                'match_id' => $matchId,
                'score' => ['home_score' => $matchItem['score'][3], 'away_score' => $matchItem['score'][4]],
                'left-seconds-in-current-matter' => $matchItem['score'][2],
                'match_trend' => $matchTrendInfo,
                'total' => ['home' => $homeNewTotalScore, 'away' => $awayNewTotalScore],
//                'players' => !empty($matchItem['players']) ? $matchItem['players'] : null
            ];
            $matchingInfoList[] = $matchingInfo;
            Cache::set('basketball-matching-info-' . $matchId, json_encode($matchingInfo));
            unset($matchingInfo);
            if (isset($matchItem['tlive'])) {
                Cache::set('basketball-match-tlive-' . $matchId, json_encode($matchItem['tlive']), 60 * 60);
                $tliveCountOld = Cache::get('basketball-match-tlive-count-' . $matchId) ?: 0;
                $tliveCountNew = 0;
                foreach ($matchItem['tlive'] as $tliveItem) {
                    foreach ($tliveItem as $signalTlive) {
                        $formatTlive[] = $signalTlive;
                    }
                    $tliveCountNew += count($tliveItem);
                }
                Cache::set('basketball-match-tlive-count-' . $matchId, $tliveCountNew, 60 * 60);
                if ($tliveCountNew > $tliveCountOld) { //有新消息要推送
                    $diff = array_slice($formatTlive, -1 * ($tliveCountNew-$tliveCountOld));
                    (new WebSocket())->basketballContentPush($diff, $matchId, $statusId);
                }
            }


        }

        //update的推送
        if (!$matchingInfoList) {
            return Log::getInstance()->info('no-basketball-match-update');

        }
        $tool = Tool::getInstance();
        $server = ServerManager::getInstance()->getSwooleServer();
        $returnData = [
            'event' => 'basketball_match_update',
            'match_info_list' => $matchingInfoList
        ];
        $onlineUsers = OnlineUser::getInstance()->table();
        foreach ($onlineUsers as $fd => $onlineUser) {
            $connection = $server->connection_info($fd);
            if (is_array($connection) && $connection['websocket_status'] == 3) {  // 用户正常在线时可以进行消息推送
                $server->push($fd, $tool->writeJson(WebSocketStatus::STATUS_SUCC, WebSocketStatus::$msg[WebSocketStatus::STATUS_SUCC], $returnData));
            } else {
                Log::getInstance()->info('basketball-lost-connection-' . $fd);
            }
        }
        return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);


    }


    /**
     * 更新篮球比赛趋势  1分钟一次
     * @throws \Throwable
     */
    public function updateBasketballMatchTrend()
    {
        if ($playingMatches = BasketballMatch::create()->field(['match_id'])->where('status_id', BasketballApi::STATUS_PLAYING, 'in')->all()) {

            foreach ($playingMatches as $playingMatch) {
                //比赛趋势
                $match_res = Tool::getInstance()->postApi(sprintf($this->matchTrend, $this->user, $this->secret, $playingMatch['match_id']));
                $match_trend = json_decode($match_res, true);

                if ($match_trend['code'] != 0) {
                    $match_trend_info = [];
                } else {
                    $match_trend_info = $match_trend['results'];
                }
                if ($matchTlive = BasketballMatchTlive::create()->where('match_id', $playingMatch['match_id'])->get()) {

                    $matchTlive->match_trend = json_encode($match_trend_info);
                    $matchTlive->update();
                } else {
                    $insertData = [
                        'match_id' => $playingMatch['match_id'],
                        'match_trend' => json_encode($match_trend_info),
                    ];

                    BasketballMatchTlive::create($insertData)->save();
                }
            }

        }

    }


    public function fixMatch()
    {

        $matchId = $this->params['match_id'];
        $url = sprintf($this->matchHistory, $this->user, $this->secret, $matchId);
        $res = Tool::getInstance()->postApi($url);
        $decodeDatas = json_decode($res, true);
        $result = $decodeDatas['results'];
        if (!$result) return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 2);

        $match_res = Tool::getInstance()->postApi(sprintf($this->matchTrend, $this->user, $this->secret, $matchId));
        $match_trend = json_decode($match_res, true);

        if ($match_trend['code'] != 0) {
            $match_trend_info = [];
        } else {
            $match_trend_info = $match_trend['results'];
        }
        $match = BasketballMatch::create()->where('match_id', $matchId)->get();

        $match->home_scores = json_encode($result['score'][3]);
        $match->away_scores = json_encode($result['score'][4]);
        $match->status_id = $result['score'][1];
        $match->update();
        if ($matchTlive = BasketballMatchTlive::create()->where('match_id', $matchId)->get()) {
            $matchTlive->score = json_encode($result['score']);
            $matchTlive->stats = json_encode($result['stats']);
            $matchTlive->tlive = json_encode($result['tlive']);
            $matchTlive->players = json_encode($result['players']);
            $matchTlive->match_trend = json_encode($match_trend_info);
            $matchTlive->update();
        } else {
            $insert = [
                'score' => json_encode($result['score']),
                'stats' => json_encode($result['stats']),
                'tlive' => json_encode($result['tlive']),
                'players' => json_encode($result['players']),
                'match_trend' => json_encode($match_trend_info),
                'match_id' => $matchId,
            ];
            BasketballMatchTlive::create($insert)->save();
        }
        return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], 1);
    }

    public function testBak()
    {

        $url = sprintf($this->seasonAllStatsDetail, $this->user, $this->secret, 1057);

        $res = Tool::getInstance()->postApi($url);
        $decodeDatas = json_decode($res, true);
        return $this->writeJson(Status::CODE_WRONG_RES, Status::$msg[Status::CODE_WRONG_RES], $decodeDatas);


        $matchingInfoList = [];
        foreach ($decodeDatas as $matchItem) {

        }
        return $this->writeJson(Statuses::CODE_OK, 'no data', $decodeDatas);

    }

    public function getMatchListDiary()
    {
        $maxUpdatedAt = BasketballMatch::create()->max('updated_at');
        $url = sprintf($this->changedMatchList, $this->user, $this->secret, (int)$maxUpdatedAt);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球比赛更新无数据');
            return $this->writeJson(Statuses::CODE_OK, 'no data');
        }

        $homeTeamIds = array_column($decodeDatas, 'home_team_id');
        $awayTeamIds = array_column($decodeDatas, 'away_team_id');
        $teamIds = array_unique(array_merge($homeTeamIds, $awayTeamIds));
        $teams = BasketballTeam::create()->where('team_id', $teamIds, 'in')->all();
        $sortTeams = $sortCompetition = [];
        array_walk($teams, function($v, $k) use(&$sortTeams) {
            $sortTeams[$v['team_id']] = $v;
        });
        $competitionsIds = array_column($decodeDatas, 'competition_id');
        $competitionArr = BasketBallCompetition::create()->where('competition_id', $competitionsIds, 'in')->all();
        array_walk($competitionArr, function($cv, $ck) use(&$sortCompetition) {
            $sortCompetition[$cv['competition_id']] = $cv;
        });

        if (!$decodeDatas) {
            Log::getInstance()->info(date('Y-d-d H:i:s') . '篮球比赛更新无数据');
        } else {
            foreach ($decodeDatas as $data) {
                if (!isset($sortTeams[$data['home_team_id']]) || !isset($sortTeams[$data['away_team_id']])) continue;
                $home_team = $sortTeams[$data['home_team_id']];
                $away_team = $sortTeams[$data['away_team_id']];
                $competition = isset($sortCompetition[$data['competition_id']]) ? $sortCompetition[$data['competition_id']] : [];

                if ($match = BasketballMatch::create()->where('match_id', $data['id'])->get()) {
                    $match->status_id = (int)$data['status_id'];
                    $match->match_time = (int)$data['match_time'];
                    $match->note = $data['note'];
                    $match->neutral = $data['neutral'];
                    $match->home_scores = json_encode($data['home_scores']);
                    $match->away_scores = json_encode($data['away_scores']);
                    $match->coverage = json_encode($data['coverage']);
                    $match->home_team_name = isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh;
                    $match->home_team_logo = isset($home_team->logo) ? $home_team->logo : '';
                    $match->away_team_name = isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh;
                    $match->away_team_logo = isset($away_team->logo) ? $away_team->logo : '';
                    $match->update();
                } else {
                    $insert = [
                        'match_id' => (int)$data['id'],
                        'competition_id' => $data['competition_id'],
                        'home_team_id' => $data['home_team_id'],
                        'away_team_id' => $data['away_team_id'],
                        'status_id' => $data['status_id'],
                        'match_time' => $data['match_time'],
                        'neutral' => $data['neutral'],
                        'note' => $data['note'],
                        'home_scores' => json_encode($data['home_scores']),
                        'away_scores' => json_encode($data['away_scores']),
                        'period_count' => $data['period_count'],
                        'kind' => $data['kind'],
                        'coverage' => json_encode($data['coverage']),
                        'season_id' => isset($data['season_id']) ? $data['season_id'] : 0,
                        'round' => isset($data['round']) ? json_encode($data['round']) : '',
                        'venue_id' => (isset($data['venue_id'])) ? (int)$data['venue_id'] : 0,
                        'position' => isset($data['position']) ? json_encode($data['position']) : '',
                        'updated_at' => $data['updated_at'],
                        'home_team_name' => isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh,
                        'home_team_logo' => isset($home_team->logo) ? $home_team->logo : '',
                        'away_team_name' => isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh,
                        'away_team_logo' => isset($away_team->logo) ? $away_team->logo : '',
                        'competition_name' => isset($competition->short_name_zh) ? $competition->short_name_zh : '',
                    ];
                    BasketballMatch::create($insert)->save();
                }

                if ($seasonMatch = BasketballMatchSeason::create()->where('match_id', $data['id'])->get()) {
                    $seasonMatch->status_id = (int)$data['status_id'];
                    $seasonMatch->match_time = (int)$data['match_time'];
                    $seasonMatch->note = $data['note'];
                    $seasonMatch->neutral = $data['neutral'];
                    $seasonMatch->home_scores = json_encode($data['home_scores']);
                    $seasonMatch->away_scores = json_encode($data['away_scores']);
                    $seasonMatch->coverage = json_encode($data['coverage']);
                    $seasonMatch->home_team_name = isset($home_team->short_name_zh) ? $home_team->name_zh : $home_team->name_zh;
                    $seasonMatch->home_team_logo = isset($home_team->logo) ? $home_team->logo : '';
                    $seasonMatch->away_team_name = isset($away_team->short_name_zh) ? $away_team->name_zh : $away_team->name_zh;
                    $seasonMatch->away_team_logo = isset($away_team->logo) ? $away_team->logo : '';
                    $seasonMatch->update();
                } else {
                    if (isset($insert)) BasketballMatchSeason::create($insert)->save();
                }
            }

        }
        return $this->writeJson(Statuses::CODE_OK, 'no data', 1);
    }


    public function deleteMatch()
    {
        $url = sprintf($this->deleteMatch, $this->user, $this->secret);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results']['match'];
        if (empty($decodeDatas)) return $this->writeJson(Statuses::CODE_OK, 'no data');

        $matchIdStr = implode(',', $decodeDatas);

        $formatStr = '(' . $matchIdStr . ')';

        $queryBuild = new QueryBuilder();
        // 支持参数绑定 第二个参数非必传
        $queryBuild->raw("update basketball_match_list set is_delete = 1 where match_id in " . $formatStr);
        $data = DbManager::getInstance()->query($queryBuild, true, 'default');
        return $this->writeJson(Statuses::CODE_OK, 'no data', $data);

    }


    public function updateTeamHonorList()
    {
        $url = sprintf($this->teamHonroList, $this->user, $this->secret, 0);
        $res = Tool::getInstance()->postApi($url);
        $teams = json_decode($res, true);
        $decodeDatas = $teams['results'];
        if (empty($decodeDatas)) return $this->writeJson(Statuses::CODE_OK, 'no data', $decodeDatas);
        foreach ($decodeDatas as $data) {
            if (!$res = BasketballTeamHonorList::create()->where('honor_id', $data['id'])->get()) {
                $formatData = [
                    'team_id' => $data['team_id'],
                    'competition_id' => $data['competition_id'],
                    'season_id' => $data['season_id'],
                    'season' => $data['season'],
                    'updated_at' => $data['updated_at'],
                    'honor_id' => $data['id'],
                ];
                BasketballTeamHonorList::create($formatData)->save();
            } else {
                $res->team_id = $data['team_id'];
                $res->competition_id = $data['competition_id'];
                $res->season_id = $data['season_id'];
                $res->season = $data['season'];
                $res->updated_at = $data['updated_at'];
                $res->update();
            }
        }
        return $this->writeJson(Statuses::CODE_OK, Statuses::$msg[Statuses::CODE_OK], 1);

    }

    /**
     * 更新球员参加过的所有赛季
     */
    public function updatePlayerInSeason()
    {
        //当前赛季有球员数据统计的赛季

        $seasonId = Cache::get('update-basketball-season-player-stats-seasonid');
        if ($seasonId) {
            $seasons = BasketballSeasonList::create()
                ->where('season_id', $seasonId, '>')
                ->where('has_player_stats', 1)->order('season_id', 'ASC')->all();
        } else {
            $seasons = BasketballSeasonList::create()
                ->where('has_player_stats', 1)->order('season_id', 'ASC')->all();
        }
        if (!$seasons) {
            return $this->writeJson(Status::CODE_OK, Status::$msg[Status::CODE_OK], 1);
        }
        foreach ($seasons as $season) {
            $selectSeasonId = $season->season_id;
            Cache::set('update-season-player-stats-seasonid', $selectSeasonId, 60 * 60);
            $seasonPlayerStats = BasketballSeasonAllStatsDetail::create()->field(['season_id', 'player_stats'])->where('season_id', $selectSeasonId)->get();

            if (!$seasonPlayerStats) continue;
            $playerStats = json_decode($seasonPlayerStats->player_stats, true);
            foreach ($playerStats as $playerStat) {
                $playerId = $playerStat['player_id'];
                $playerSeason = BasketballPlayer::create()->where('player_id', $playerId)->get();

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


}