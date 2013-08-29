<?php

namespace App\Filter;

/**
 * Делает уникальный хеш
 *
 * Алгоритм, строка разбивается на пробелы, вырезаются stop-слова
 * обрабатывается стемером, слова сортируются по имени и удаляются дубликаты
 * склеиваем все обратно и берем hash
 */
class StemHash extends StripText
{
	public function filter($value)
	{
		// Обрабатываем имя
		$value = parent::filter($value);

        // Убираем стоп слова
		$value = Zend_Filter::filterStatic($value, 'Stopwords');

		// Берем stem
		$value = Zend_Filter::filterStatic($value, 'Stem');

		// Сортируем слова по алфавиту
		$wordsArray = array_unique(explode(' ', $value));
		asort($wordsArray);

		return sha1(implode(' ', $wordsArray));
	}
}
