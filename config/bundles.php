<?php

return [
	Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => [ 'all' => true ],
	Doctrine\Bundle\FixturesBundle\DoctrineFixturesBundle::class => [ 'dev' => true, 'test' => true ],
	Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle::class => [ 'all' => true ],
	EightPoints\Bundle\GuzzleBundle\EightPointsGuzzleBundle::class => [ 'all' => true ],
	Symfony\Bundle\DebugBundle\DebugBundle::class => [ 'dev' => true, 'test' => true ],
	Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => [ 'all' => true ],
	Symfony\Bundle\MonologBundle\MonologBundle::class => [ 'all' => true ],
	Symfony\Bundle\TwigBundle\TwigBundle::class => [ 'all' => true ],
	Symfony\Bundle\WebProfilerBundle\WebProfilerBundle::class => [ 'dev' => true, 'test' => true ],
	Wikimedia\ToolforgeBundle\ToolforgeBundle::class => [ 'all' => true ],
	Nelmio\Alice\Bridge\Symfony\NelmioAliceBundle::class => [ 'dev' => true, 'test' => true ],
	Symfony\Bundle\MakerBundle\MakerBundle::class => [ 'dev' => true ],
	Symfony\WebpackEncoreBundle\WebpackEncoreBundle::class => [ 'all' => true ],
];
