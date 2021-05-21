<?php

namespace App\Http\Controllers;

use App\Article;
use App\ArticlesDataTable;
use App\ArticleSection;
use App\Author;
use App\Conversion;
use App\Http\Request;
use App\Http\Resources\SectionResource;
use App\Model\Tag;
use App\Section;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Html;

class SectionController extends Controller
{
    public function index(Request $request)
    {
        return response()->format([
            'html' => view('sections.index', [
                'sections' => Section::all()->pluck('name', 'id'),
                'contentTypes' => array_merge(
                    ['all'],
                    Article::groupBy('content_type')->pluck('content_type')->toArray()
                ),
                'publishedFrom' => $request->input('published_from', 'today - 30 days'),
                'publishedTo' => $request->input('published_to', 'now'),
                'conversionFrom' => $request->input('conversion_from', 'today - 30 days'),
                'conversionTo' => $request->input('conversion_to', 'now'),
                'contentType' => $request->input('content_type', 'all'),
            ]),
            'json' => SectionResource::collection(Section::paginate()),
        ]);
    }

    public function show(Section $section, Request $request)
    {
        return response()->format([
            'html' => view('sections.show', [
                'section' => $section,
                'contentTypes' => Article::groupBy('content_type')->pluck('content_type', 'content_type'),
                'authors' => Author::all()->pluck('name', 'id'),
                'tags' => Tag::all()->pluck('name', 'id'),
                'sections' => Section::all()->pluck('name', 'id'),
                'publishedFrom' => $request->input('published_from', 'today - 2 days'),
                'publishedTo' => $request->input('published_to', 'now'),
                'conversionFrom' => $request->input('conversion_from', 'today - 2 days'),
                'conversionTo' => $request->input('conversion_to', 'now'),
            ]),
            'json' => new SectionResource($section),
        ]);
    }

    public function dtSections(Request $request, DataTables $datatables)
    {
        $cols = [
            'sections.id',
            'sections.name',
            'COALESCE(articles_count, 0) AS articles_count',
            'COALESCE(conversions_count, 0) AS conversions_count',
            'COALESCE(conversions_amount, 0) AS conversions_amount',
            'COALESCE(pageviews_all, 0) AS pageviews_all',
            'COALESCE(pageviews_not_subscribed, 0) AS pageviews_not_subscribed',
            'COALESCE(pageviews_subscribers, 0) AS pageviews_subscribers',
            'COALESCE(timespent_all, 0) AS timespent_all',
            'COALESCE(timespent_not_subscribed, 0) AS timespent_not_subscribed',
            'COALESCE(timespent_subscribers, 0) AS timespent_subscribers',
            'COALESCE(timespent_all / pageviews_all, 0) AS avg_timespent_all',
            'COALESCE(timespent_not_subscribed / pageviews_not_subscribed, 0) AS avg_timespent_not_subscribed',
            'COALESCE(timespent_subscribers / pageviews_subscribers, 0) AS avg_timespent_subscribers',
        ];

        $sectionArticlesQuery = ArticleSection::selectRaw(implode(',', [
            'section_id',
            'COUNT(*) as articles_count'
        ]))
            ->leftJoin('articles', 'article_section.article_id', '=', 'articles.id')
            ->groupBy('section_id');

        if ($request->input('content_type') && $request->input('content_type') !== 'all') {
            $sectionArticlesQuery->where('content_type', '=', $request->input('content_type'));
        }

        $conversionsQuery = Conversion::selectRaw(implode(',', [
            'section_id',
            'count(distinct conversions.id) as conversions_count',
            'sum(conversions.amount) as conversions_amount',
        ]))
            ->leftJoin('article_section', 'conversions.article_id', '=', 'article_section.article_id')
            ->leftJoin('articles', 'article_section.article_id', '=', 'articles.id')
            ->groupBy('section_id');

        $pageviewsQuery = Article::selectRaw(implode(',', [
            'section_id',
            'COALESCE(SUM(pageviews_all), 0) AS pageviews_all',
            'COALESCE(SUM(pageviews_all) - SUM(pageviews_subscribers), 0) AS pageviews_not_subscribed',
            'COALESCE(SUM(pageviews_subscribers), 0) AS pageviews_subscribers',
            'COALESCE(SUM(timespent_all), 0) AS timespent_all',
            'COALESCE(SUM(timespent_all) - SUM(timespent_subscribers), 0) AS timespent_not_subscribed',
            'COALESCE(SUM(timespent_subscribers), 0) AS timespent_subscribers',
        ]))
            ->leftJoin('article_section', 'articles.id', '=', 'article_section.article_id')
            ->groupBy('section_id');

        if ($request->input('content_type') && $request->input('content_type') !== 'all') {
            $pageviewsQuery->where('content_type', '=', $request->input('content_type'));
            $conversionsQuery->where('content_type', '=', $request->input('content_type'));
        }

        if ($request->input('published_from')) {
            $publishedFrom = Carbon::parse($request->input('published_from'), $request->input('tz'));
            $sectionArticlesQuery->where('published_at', '>=', $publishedFrom);
            $conversionsQuery->where('published_at', '>=', $publishedFrom);
            $pageviewsQuery->where('published_at', '>=', $publishedFrom);
        }

        if ($request->input('published_to')) {
            $publishedTo = Carbon::parse($request->input('published_to'), $request->input('tz'));
            $sectionArticlesQuery->where('published_at', '<=', $publishedTo);
            $conversionsQuery->where('published_at', '<=', $publishedTo);
            $pageviewsQuery->where('published_at', '<=', $publishedTo);
        }

        if ($request->input('conversion_from')) {
            $conversionFrom = Carbon::parse($request->input('conversion_from'), $request->input('tz'));
            $conversionsQuery->where('paid_at', '>=', $conversionFrom);
        }
        if ($request->input('conversion_to')) {
            $conversionTo = Carbon::parse($request->input('conversion_to'), $request->input('tz'));
            $conversionsQuery->where('paid_at', '<=', $conversionTo);
        }

        $sections = Section::selectRaw(implode(",", $cols))
            ->leftJoin(DB::raw("({$sectionArticlesQuery->toSql()}) as aa"), 'sections.id', '=', 'aa.section_id')->addBinding($sectionArticlesQuery->getBindings())
            ->leftJoin(DB::raw("({$conversionsQuery->toSql()}) as c"), 'sections.id', '=', 'c.section_id')->addBinding($conversionsQuery->getBindings())
            ->leftJoin(DB::raw("({$pageviewsQuery->toSql()}) as pv"), 'sections.id', '=', 'pv.section_id')->addBinding($pageviewsQuery->getBindings())
            ->groupBy(['sections.name', 'sections.id', 'articles_count', 'conversions_count', 'conversions_amount', 'pageviews_all',
                'pageviews_not_subscribed', 'pageviews_subscribers', 'timespent_all', 'timespent_not_subscribed', 'timespent_subscribers']);

        $conversionsQuery = \DB::table('conversions')
            ->selectRaw('count(distinct conversions.id) as count, sum(amount) as sum, currency, article_section.section_id')
            ->join('article_section', 'conversions.article_id', '=', 'article_section.article_id')
            ->join('articles', 'article_section.article_id', '=', 'articles.id')
            ->groupBy(['article_section.section_id', 'conversions.currency']);

        if ($request->input('content_type') && $request->input('content_type') !== 'all') {
            $conversionsQuery->where('content_type', '=', $request->input('content_type'));
        }

        if ($request->input('published_from')) {
            $conversionsQuery->where('published_at', '>=', Carbon::parse($request->input('published_from'), $request->input('tz')));
        }
        if ($request->input('published_to')) {
            $conversionsQuery->where('published_at', '<=', Carbon::parse($request->input('published_to'), $request->input('tz')));
        }
        if ($request->input('conversion_from')) {
            $conversionFrom = Carbon::parse($request->input('conversion_from'), $request->input('tz'));
            $conversionsQuery->where('paid_at', '>=', $conversionFrom);
        }
        if ($request->input('conversion_to')) {
            $conversionTo = Carbon::parse($request->input('conversion_to'), $request->input('tz'));
            $conversionsQuery->where('paid_at', '<=', $conversionTo);
        }

        $conversionAmounts = [];
        $conversionCounts = [];
        foreach ($conversionsQuery->get() as $record) {
            $conversionAmounts[$record->section_id][$record->currency] = $record->sum;
            $conversionCounts[$record->section_id] = $record->count;
        }

        return $datatables->of($sections)
            ->addColumn('name', function (Section $section) {
                return Html::linkRoute('sections.show', $section->name, $section);
            })
            ->filterColumn('name', function (Builder $query, $value) {
                $sectionIds = explode(',', $value);
                $query->where(function (Builder $query) use ($sectionIds, $value) {
                    $query->where('sections.name', 'like', '%' . $value . '%')
                        ->orWhereIn('sections.id', $sectionIds);
                });
            })
            ->addColumn('conversions_count', function (Section $section) use ($conversionCounts) {
                return $conversionCounts[$section->id] ?? 0;
            })
            ->addColumn('conversions_amount', function (Section $section) use ($conversionAmounts) {
                if (!isset($conversionAmounts[$section->id])) {
                    return 0;
                }
                $amounts = [];
                foreach ($conversionAmounts[$section->id] as $currency => $c) {
                    $c = round($c, 2);
                    $amounts[] = "{$c} {$currency}";
                }
                return $amounts ?? [0];
            })
            ->orderColumn('conversions_count', 'conversions_count $1')
            ->orderColumn('conversions_amount', 'conversions_amount $1')
            ->make(true);
    }

    public function dtArticles(Section $section, Request $request, Datatables $datatables, ArticlesDataTable $articlesDataTable)
    {
        ini_set('memory_limit', '512M');

        $articlesDataTable->setSection($section);
        return $articlesDataTable->getDataTable($request, $datatables);
    }
}
