<?php

namespace App\Modules\Stories\DTO;

class StoryFeedDTO
{
    public function __construct(
        public array $stories,
        public int $currentPage,
        public int $totalPages,
        public array $newCommentsMap,
        public array $bannedDomainsCache,
        public string $sort,
        public string $domain,
        public ?string $author,
        public int $currentUserId,
        public bool $isAdmin,
        public bool $canUserDownvote,
        public array $currentVotes,
        public array $rssFeed,
        public string $pageTitle,
        public array $extraData = []
    ) {}
}
