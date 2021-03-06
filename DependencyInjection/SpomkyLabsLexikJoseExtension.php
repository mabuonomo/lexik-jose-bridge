<?php

/*
 * The MIT License (MIT)
 *
 * Copyright (c) 2014-2018 Spomky-Labs
 *
 * This software may be modified and distributed under the terms
 * of the MIT license.  See the LICENSE file for details.
 */

namespace SpomkyLabs\LexikJoseBundle\DependencyInjection;

use Jose\Bundle\JoseFramework\Helper\ConfigurationHelper;
use SpomkyLabs\LexikJoseBundle\Encoder\LexikJoseEncoder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

final class SpomkyLabsLexikJoseExtension extends Extension implements PrependExtensionInterface
{
    /**
     * @return string
     */
    public function getAlias():string
    {
        return 'lexik_jose';
    }

    /**
     * @param array $configs
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container):void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('lexik_jose_bridge.encoder.key_index', $config['key_index']);
        $container->setParameter('lexik_jose_bridge.encoder.signature_algorithm', $config['signature_algorithm']);
        $container->setParameter('lexik_jose_bridge.encoder.issuer', $config['server_name']);

        if (!isset($config['audience'])) {
            $config['audience'] = $config['server_name'];
        }

        $container->setParameter('lexik_jose_bridge.encoder.audience', $config['audience']);
        $container->setParameter('lexik_jose_bridge.encoder.ttl', $config['ttl']);
        $container->setParameter('lexik_jose_bridge.encoder.claim_checked', $config['claim_checked']);

        $container->setParameter('lexik_jose_bridge.encoder.encryption.enabled', $config['encryption']['enabled']);
        if (true === $config['encryption']['enabled']) {
            $container->setParameter(
                'lexik_jose_bridge.encoder.encryption.key_index',
                $config['encryption']['key_index']
            );
            $container->setParameter(
                'lexik_jose_bridge.encoder.encryption.key_encryption_algorithm',
                $config['encryption']['key_encryption_algorithm']
            );
            $container->setParameter(
                'lexik_jose_bridge.encoder.encryption.content_encryption_algorithm',
                $config['encryption']['content_encryption_algorithm']
            );
            $this->loadEncryptionServices($container);
        }

        $this->loadServices($container);
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function loadServices(ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function loadEncryptionServices(ContainerBuilder $container)
    {
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('encryption_services.yml');
    }

    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $container):void
    {
        $isDebug = $container->getParameter('kernel.debug');
//        $bridgeConfig = current($container->getExtensionConfig($this->getAlias()));

        $bridgeConfig = $container->getExtensionConfig($this->getAlias());
        $bridgeConfig = $container->getParameterBag()->resolveValue($bridgeConfig);
        $bridgeConfig = $this->processConfiguration(new Configuration(), $bridgeConfig);

        if (!array_key_exists('claim_checked', $bridgeConfig)) {
            $bridgeConfig['claim_checked'] = [];
        }
        $claim_aliases = array_merge(
            $bridgeConfig['claim_checked'],
            ['exp', 'iat', 'lexik_jose_audience', 'lexik_jose_issuer']
        );
        ConfigurationHelper::addJWSBuilder(
            $container,
            $this->getAlias(),
            [
                $bridgeConfig['signature_algorithm']
            ],
            $isDebug
        );

        ConfigurationHelper::addJWSVerifier(
            $container,
            $this->getAlias(),
            [
                $bridgeConfig['signature_algorithm']
            ],
            $isDebug
        );

        ConfigurationHelper::addClaimChecker(
            $container,
            $this->getAlias(),
            $claim_aliases,
            $isDebug
        );

        ConfigurationHelper::addHeaderChecker(
            $container,
            $this->getAlias() . '_signature',
            [
                'lexik_jose_signature_algorithm'
            ]
        );

        ConfigurationHelper::addKeyset(
            $container,
            'lexik_jose_bridge.signature',
            'jwkset',
            [
                'value' => $bridgeConfig['key_set_url'],
                'is_public' => $isDebug
            ]
        );

        if (isset($bridgeConfig['encryption']['enabled']) && (true === $bridgeConfig['encryption']['enabled'])) {
            $this->enableEncryptionSupport($container, $bridgeConfig, $isDebug);
        }

        $lexikConfig = ['encoder' => ['service' => LexikJoseEncoder::class]];
        $container->prependExtensionConfig('lexik_jwt_authentication', $lexikConfig);
    }

    public function getContent($file, $url, $hours = 24)
    {
        if (\file_exists($file)) {
            $current_time = \time();
            $expire_time = $hours * 60 * 60;
            $file_time = \filemtime($file);
            if ($current_time - $expire_time < $file_time) {
                return \file_get_contents($file);
            }
        }

        $content = \file_get_contents($url);
        \file_put_contents($file, $content);

        return $content;
    }

    /**
     * @param ContainerBuilder $container
     * @param array $bridgeConfig
     * @param bool $isDebug
     */
    private function enableEncryptionSupport(ContainerBuilder $container, array $bridgeConfig, bool $isDebug)
    {
        ConfigurationHelper::addJWEBuilder(
            $container,
            $this->getAlias(),
            [
                $bridgeConfig['encryption']['key_encryption_algorithm']
            ],
            [
                $bridgeConfig['encryption']['content_encryption_algorithm']
            ],
            [
                'DEF'
            ],
            $isDebug
        );
        ConfigurationHelper::addJWEDecrypter(
            $container,
            $this->getAlias(),
            [
                $bridgeConfig['encryption']['key_encryption_algorithm']
            ],
            [
                $bridgeConfig['encryption']['content_encryption_algorithm']
            ],
            [
                'DEF'
            ],
            $isDebug
        );
        ConfigurationHelper::addHeaderChecker(
            $container,
            $this->getAlias() . '_encryption',
            [
                'lexik_jose_audience',
                'lexik_jose_issuer',
                'lexik_jose_key_encryption_algorithm',
                'lexik_jose_content_encryption_algorithm'
            ]
        );
        ConfigurationHelper::addKeyset(
            $container,
            'lexik_jose_bridge.encryption',
            'jwkset',
            [
                'value' => $bridgeConfig['encryption']['key_set_url'],
                'is_public' => $isDebug
            ]
        );
    }
}
