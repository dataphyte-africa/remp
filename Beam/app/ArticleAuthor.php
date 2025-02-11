<?php

namespace App;

use App\Model\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Remp\Journal\TokenProvider;

class ArticleAuthor extends BaseModel
{
    protected $table = 'article_author';

    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function scopeOfSelectedProperty($query)
    {
        $tokenProvider = resolve(TokenProvider::class);
        $propertyUuid = $tokenProvider->getToken();
        if ($propertyUuid) {
            $query->whereHas('article', function (Builder $articleQuery) {
                $articleQuery->ofSelectedProperty();
            });
        }
        return $query;
    }
}
