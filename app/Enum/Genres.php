<?php

declare(strict_types=1);

namespace App\Enum;

enum Genres: string
{
    case Default = 'All';
    case Action = 'Action';
    case Adult = 'Adult';
    case Adventure = 'Adventure';
    case Anime = 'Anime';
    case Crime = 'Crime';
    case Comedy = 'Comedy';
    case DIY = 'DIY';
    case Drama = 'Drama';
    case Espionage = 'Espionage';
    case Family = 'Family';
    case Fantasy = 'Fantasy';
    case Food = 'Food';
    case History = 'History';
    case Horror = 'Horror';
    case Legal = 'Legal';
    case Medical = 'Medical';
    case Mystery = 'Mystery';
    case Music = 'Music';
    case Nature = 'Nature';
    case Romance = 'Romance';
    case ScienceFiction = 'Science-Fiction';
    case Sports = 'Sports';
    case Supernatural = 'Supernatural';
    case Thriller = 'Thriller';
    case Travel = 'Travel';
    case War = 'War';
    case Western = 'Western';
}
