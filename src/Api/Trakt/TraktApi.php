<?php declare(strict_types=1);

namespace Movary\Api\Trakt;

use Movary\Api\Trakt\Cache\User\Movie\Watched;
use Movary\Api\Trakt\ValueObject\TraktCredentials;
use Movary\Api\Trakt\ValueObject\TraktId;
use Movary\Api\Trakt\ValueObject\User;
use RuntimeException;

class TraktApi
{
    public function __construct(
        private readonly TraktClient $client,
        private readonly Watched\Service $cacheWatchedService,
    ) {
    }

    public function fetchUniqueCachedTraktIds(int $userId) : array
    {
        return $this->cacheWatchedService->fetchAllUniqueTraktIds($userId);
    }

    public function fetchUserMovieHistoryByMovieId(TraktCredentials $traktCredentials, TraktId $traktId) : User\Movie\History\DtoList
    {
        $responseData = $this->client->get(
            $traktCredentials->getClientId(),
            sprintf(
                '/users/%s/history/movies/%d',
                $traktCredentials->getUsername(),
                $traktId->asInt(),
            ),
        );

        return User\Movie\History\DtoList::createFromArray($responseData);
    }

    public function fetchUserMoviesRatings(TraktCredentials $traktCredentials) : User\Movie\Rating\DtoList
    {
        $responseData = $this->client->get(
            $traktCredentials->getClientId(),
            sprintf('/users/%s/ratings/movies', $traktCredentials->getUsername()),
        );

        return User\Movie\Rating\DtoList::createFromArray($responseData);
    }

    public function fetchUserMoviesWatched(TraktCredentials $traktCredentials) : User\Movie\Watched\DtoList
    {
        $responseData = $this->client->get(
            $traktCredentials->getClientId(),
            sprintf('/users/%s/watched/movies', $traktCredentials->getUsername()),
        );

        return User\Movie\Watched\DtoList::createFromArray($responseData);
    }

    public function removeWatchCacheByTraktId(int $userId, TraktId $traktId) : void
    {
        $this->cacheWatchedService->removeLastUpdated($userId, $traktId);
    }

public function verifyCredentials(string $clientId, string $username) : bool
{
    if ($username === 'service_account') {
        $apiKey = getenv('TRAKT_API_KEY');
        $path = sprintf('/users/%s/watched/movies?api_key=%s', $username, $apiKey);
    } else {
        $path = sprintf('/users/%s/watched/movies', $username);
    }

    $response = $this->client->getResponse($clientId, $path);

    if ($response->getStatusCode() === 200) {
        return true;
    }

    if ($response->getStatusCode() === 403 || $response->getStatusCode() === 404 || $response->getStatusCode() === 405) {
        return false;
    }

    throw new RuntimeException('Trakt api error. Response status code: ' . $response->getStatusCode());
}
}
