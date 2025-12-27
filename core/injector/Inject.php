<?php
/**
 * Property injection attribute
 * #[Inject] private Service $service;
 */
namespace Sophia\Injector;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Inject {}