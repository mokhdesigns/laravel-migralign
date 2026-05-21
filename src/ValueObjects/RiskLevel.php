<?php

namespace MigrAlign\ValueObjects;

enum RiskLevel: string
{
    case Safe = 'safe';
    case Risky = 'risky';
    case Destructive = 'destructive';
}
