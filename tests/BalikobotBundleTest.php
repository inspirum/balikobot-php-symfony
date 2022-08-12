<?php

declare(strict_types=1);

namespace Inspirum\Balikobot\Integration\Symfony\Tests;

use Inspirum\Balikobot\Integration\Symfony\BalikobotBundle;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

final class BalikobotBundleTest extends TestCase
{
    public function testBundle(): void
    {
        $kernel = new class ('dev', true) extends Kernel {
            /**
             * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
             */
            public function registerBundles(): iterable
            {
                return [new BalikobotBundle()];
            }

            public function registerContainerConfiguration(LoaderInterface $loader): void
            {
                $loader->load(__DIR__ . '/config/config.yaml');
                $loader->load(__DIR__ . '/config/balikobot.yaml');
            }
        };

        try {
            $kernel->boot();

            /** @var \Inspirum\Balikobot\Integration\Symfony\Tests\Service $service */
            $service = $kernel->getContainer()->get(Service::class);

            self::assertInstanceOf(Service::class, $service);

            $requester = new ReflectionClass($service->defaultCurlRequester);
            $apiUser   = $requester->getProperty('apiUser');
            $apiKey    = $requester->getProperty('apiKey');

            self::assertSame('testUser', $apiUser->getValue($service->defaultCurlRequester));
            self::assertSame('testKey1', $apiKey->getValue($service->defaultCurlRequester));
        } finally {
            (new Filesystem())->remove($kernel->getCacheDir());
        }
    }
}
