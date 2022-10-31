<?php

namespace App\Models;

use App\Exceptions\DuplicateVoteException;
use App\Exceptions\VoteNotFoundException;
use Cviebrock\EloquentSluggable\Sluggable;
use DivisionByZeroError;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin IdeHelperPoll
 */
class Poll extends Model
{
    use HasFactory, Sluggable;

    protected $guarded = [];
    protected $perPage = 10;

    protected $casts = [
        'ends_at' => 'datetime',
    ];

    protected $fillable = [
        'title',
        'slug',
        'description',
        'category_id',
        'status_id',
        'ends_at'
    ];

    public static function booted()
    {
        // Set some sane defaults on create
        static::creating(function (Poll $poll) {
            $poll->votes_yes = 0;
            $poll->votes_no = 0;
        });
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Return the sluggable configuration array for this model.
     *
     * @return array
     */
    public function sluggable(): array
    {
        return [
            'slug' => [
                'source' => 'title'
            ]
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function votes()
    {
        return $this->belongsToMany(User::class, 'votes');
    }

    public function isVotedByUser(?User $user)
    {
        if (!$user) {
            return false;
        }

        return Vote::where('user_id', $user->id)
            ->where('poll_id', $this->id)
            ->exists();
    }

    public function voteYes(?User $user)
    {
        try {
            if($this->vote($user)) {
                $this->increment('votes_yes');
            }
        } catch (DuplicateVoteException $e) {
            //
        }
    }

    public function voteNo(?User $user)
    {
        try {
            if($this->vote($user)) {
                $this->increment('votes_no');
            }
        } catch (DuplicateVoteException $e) {
            //
        }
    }

    public function vote(?User $user): bool
    {
        // If we don't have a user, we can't vote on the poll
        if (!$user) {
            return false;
        }

        // We store that the user _has_ voted on this poll to stop duplicates,
        // but we do not store the actual vote against the user.

        if ($this->isVotedByUser($user)) {
            throw new DuplicateVoteException;
        }

        /** @var Vote $vote */
        $vote = Vote::create([
            'poll_id' => $this->id,
            'user_id' => $user->id,
        ]);

        return $vote->exists();
    }

    public function asPercent(): float
    {
        try {
            return $this->votes_no / ($this->votes_yes + $this->votes_no) * 100;
        } catch (DivisionByZeroError $e) {
            return 0;
        }
    }

    public function openForVoting(): bool
    {
        return $this->status->name === 'Open';
    }
}
