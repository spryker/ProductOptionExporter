<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Yves\Kernel;

use Pyz\Yves\Application\Plugin\Pimple;
use Spryker\Client\Kernel\ClassResolver\Client\ClientResolver;
use Spryker\Shared\Kernel\Dependency\Injection\DependencyInjectionProviderCollectionInterface;
use Spryker\Shared\Kernel\Dependency\Injection\DependencyInjector;
use Spryker\Yves\Kernel\ClassResolver\DependencyInjectionProvider\DependencyInjectionProviderResolver;
use Spryker\Yves\Kernel\ClassResolver\DependencyProvider\DependencyProviderResolver;
use Spryker\Yves\Kernel\Exception\Container\ContainerKeyNotFoundException;

abstract class AbstractFactory implements FactoryInterface
{

    /**
     * @var \Spryker\Yves\Kernel\Container $container
     */
    private $container;

    /**
     * @var \Spryker\Client\Kernel\AbstractClient
     */
    private $client;

    /**
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function getContainer()
    {
        return new Container();
    }

    /**
     * @deprecated Use DependencyProvider instead
     *
     * @return \Generated\Client\Ide\AutoCompletion|\Spryker\Shared\Kernel\LocatorLocatorInterface
     */
    protected function getLocator()
    {
        return Locator::getInstance();
    }

    /**
     * @return \Spryker\Client\Kernel\AbstractClient
     */
    protected function getClient()
    {
        if ($this->client === null) {
            $this->client = $this->resolveClient();
        }

        return $this->client;
    }

    /**
     * @throws \Spryker\Client\Kernel\ClassResolver\Client\ClientNotFoundException
     *
     * @return \Spryker\Client\Kernel\AbstractClient
     */
    protected function resolveClient()
    {
        return $this->getClientResolver()->resolve($this);
    }

    /**
     * @return \Spryker\Client\Kernel\ClassResolver\Client\ClientResolver
     */
    protected function getClientResolver()
    {
        return new ClientResolver();
    }

    /**
     * @return \Symfony\Component\Form\FormFactoryInterface
     */
    protected function getFormFactory()
    {
        return (new Pimple())->getApplication()['form.factory'];
    }

    /**
     * @param string $key
     *
     * @throws \Spryker\Yves\Kernel\Exception\Container\ContainerKeyNotFoundException
     *
     * @return mixed
     */
    protected function getProvidedDependency($key)
    {
        if ($this->container === null) {
            $this->container = $this->getContainerWithProvidedDependencies();
        }

        if ($this->container->offsetExists($key) === false) {
            throw new ContainerKeyNotFoundException($this, $key);
        }

        return $this->container[$key];
    }

    /**
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function getContainerWithProvidedDependencies()
    {
        $container = $this->getContainer();
        $dependencyInjectionProviderCollection = $this->resolveDependencyInjectionProviderCollection();
        $dependencyInjector = $this->getDependencyInjector($dependencyInjectionProviderCollection);
        $dependencyProvider = $this->resolveDependencyProvider();

        $container = $this->provideDependencies($dependencyProvider, $container);
        $container = $dependencyInjector->inject($container);

        return $container;
    }

    /**
     * @param \Spryker\Yves\Kernel\AbstractBundleDependencyProvider $dependencyProvider
     * @param \Spryker\Yves\Kernel\Container $container
     *
     * @return \Spryker\Yves\Kernel\Container
     */
    protected function provideDependencies(AbstractBundleDependencyProvider $dependencyProvider, Container $container)
    {
        return $dependencyProvider->provideDependencies($container);
    }

    /**
     * @return \Spryker\Shared\Kernel\Dependency\Injection\DependencyInjectionProviderCollectionInterface
     */
    protected function resolveDependencyInjectionProviderCollection()
    {
        return $this->getDependencyInjectionProviderResolver()->resolve($this);
    }

    /**
     * @param \Spryker\Shared\Kernel\Dependency\Injection\DependencyInjectionProviderCollectionInterface $dependencyInjectionProviderCollection
     *
     * @return \Spryker\Shared\Kernel\Dependency\Injection\DependencyInjector
     */
    protected function getDependencyInjector(DependencyInjectionProviderCollectionInterface $dependencyInjectionProviderCollection)
    {
        return new DependencyInjector($dependencyInjectionProviderCollection);
    }

    /**
     * @return \Spryker\Yves\Kernel\ClassResolver\DependencyInjectionProvider\DependencyInjectionProviderResolver
     */
    protected function getDependencyInjectionProviderResolver()
    {
        return new DependencyInjectionProviderResolver();
    }

    /**
     * @return \Spryker\Yves\Kernel\AbstractBundleDependencyProvider
     */
    protected function resolveDependencyProvider()
    {
        return $this->getDependencyProviderResolver()->resolve($this);
    }

    /**
     * @return \Spryker\Yves\Kernel\ClassResolver\DependencyProvider\DependencyProviderResolver
     */
    protected function getDependencyProviderResolver()
    {
        return new DependencyProviderResolver();
    }

}
