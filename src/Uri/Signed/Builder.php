<?php

namespace Zenstruck\Uri\Signed;

use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\UriSigner as LegacyUriSigner;
use Zenstruck\Uri;
use Zenstruck\Uri\SignedUri;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 *
 * @immutable
 */
final class Builder implements \Stringable
{
    private Uri $uri;
    private UriSigner|LegacyUriSigner $signer; // @phpstan-ignore-line
    private ?\DateTimeImmutable $expiresAt = null;
    private ?string $singleUseToken = null;

    /**
     * @internal
     *
     * @param string|UriSigner|LegacyUriSigner $secret
     */
    public function __construct(Uri $uri, $secret) // @phpstan-ignore-line
    {
        if (!\class_exists(UriSigner::class) && !\class_exists(LegacyUriSigner::class)) {
            throw new \LogicException('symfony/http-kernel is required to sign URIs. composer require symfony/http-kernel.');
        }

        if ($uri instanceof SignedUri) {
            throw new \LogicException(\sprintf('"%s" is already signed.', $uri));
        }

        $this->uri = $uri;
        $this->signer = match(true) {
            $secret instanceof UriSigner, $secret instanceof LegacyUriSigner => $secret, // @phpstan-ignore-line
            \class_exists(UriSigner::class) => new UriSigner($secret),
            default => new LegacyUriSigner($secret), // @phpstan-ignore-line
        };
    }

    public function __toString(): string
    {
        return $this->create();
    }

    /**
     * Set an expiry for the signed url.
     *
     * @param \DateTimeInterface|\DateInterval|string|int $when \DateTimeInterface: the exact time the link should expire
     *                                                          \DateInterval: the interval to be added to the current time
     *                                                          string: used to construct a datetime object (ie "+1 hour")
     *                                                          int: # of seconds until the link expires
     */
    public function expires($when): self
    {
        if (\is_numeric($when)) {
            $when = \DateTimeImmutable::createFromFormat('U', (string) (\time() + $when));
        }

        if (\is_string($when)) {
            $when = new \DateTimeImmutable($when);
        }

        if ($when instanceof \DateInterval) {
            $when = (new \DateTime('now'))->add($when);
        }

        if ($when instanceof \DateTime) {
            $when = \DateTimeImmutable::createFromMutable($when);
        }

        if (!$when instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException(\sprintf('%s is not a valid expires at.', get_debug_type($when)));
        }

        $clone = clone $this;
        $clone->expiresAt = $when;

        return $clone;
    }

    /**
     * Make the signed url "single-use".
     *
     * @param string $token This value MUST change once the URL is considered "used"
     */
    public function singleUse(string $token): self
    {
        $clone = clone $this;
        $clone->singleUseToken = $token;

        return $clone;
    }

    public function create(): SignedUri
    {
        return SignedUri::new($this);
    }

    /**
     * @internal
     *
     * @return array{0:Uri,1:UriSigner|LegacyUriSigner,2:\DateTimeImmutable|null,3:string|null}
     */
    public function context(): array // @phpstan-ignore-line
    {
        return [$this->uri, $this->signer, $this->expiresAt, $this->singleUseToken];
    }
}
