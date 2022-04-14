up:
	docker-compose up -d

shell:
	docker exec -it candibot_php bash

search:
	docker exec -it candibot_php sh -c "php artisan candilib:crawl --limit 1000 --refreshRate 2"

search-alix:
	php artisan candilib:crawl --limit 1000 --refreshRate 2 --postalCodes 95,94,93,92,91,78,77,76
