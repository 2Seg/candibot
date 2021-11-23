up:
	docker-compose up -d

shell:
	docker exec -it candibot_php bash

search:
	docker exec -it candibot_php sh -c "php artisan candilib:crawl --limit 1000 --refreshRate 2"
