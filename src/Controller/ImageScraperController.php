<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;

class ImageScraperController extends AbstractController
{
    public function index(): Response
    {
        return $this->render('image_scraper/index.html.twig');
    }


    public function scrape(Request $request): Response
    {
        // Получаем URL из запроса
        $url = $request->request->get('url');

        // Если URL не предоставлен, перенаправляем на домашнюю страницу
        if (!$url) {
            return $this->redirectToRoute('home');
        }

        // Создаем HTTP-клиента для выполнения запросов
        $client = new Client();

        // Выполняем GET-запрос к предоставленному URL
        $response = $client->get($url);

        // Получаем HTML-контент страницы
        $html = (string) $response->getBody();

        // Используем Crawler для парсинга HTML-контента
        $crawler = new Crawler($html);

        // Извлекаем все теги <img> и получаем значения атрибута 'src'
        $images = $crawler->filter('img')->each(function (Crawler $node) {
            return $node->attr('src');
        });

        // Массив для хранения данных об изображениях
        $imageData = [];

        // Переменная для хранения общего размера изображений
        $totalSize = 0;

        // Обрабатываем каждый найденный URL изображения
        foreach ($images as $image) {
            // Проверяем, является ли URL абсолютным, если нет, делаем его абсолютным
            if (filter_var($image, FILTER_VALIDATE_URL) === false) {
                $image = rtrim($url, '/') . '/' . ltrim($image, '/');
            }

            try {
                // Выполняем HEAD-запрос для получения информации об изображении
                $imageResponse = $client->head($image);

                // Получаем размер изображения из заголовка 'Content-Length'
                $size = $imageResponse->getHeaderLine('Content-Length');

                // Добавляем размер изображения к общему размеру
                $totalSize += (int) $size;

                // Добавляем данные об изображении в массив
                $imageData[] = [
                    'url' => $image,
                    'size' => $size,
                ];
            } catch (Exception $e) {
                // Обработка исключений, если запрос к изображению не удался
            }
        }

        // Рендерим шаблон с результатами
        return $this->render('image_scraper/result.html.twig', [
            'images' => $imageData,
            'totalImages' => count($imageData),
            'totalSize' => round($totalSize / (1024 * 1024), 2), // Конвертация в MB
        ]);
    }
}
