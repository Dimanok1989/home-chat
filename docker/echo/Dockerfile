FROM node:22-alpine

RUN npm install -g laravel-echo-server

WORKDIR /app
COPY docker/echo/echo-server-config.json /app/laravel-echo-server.json
COPY docker/echo/start.sh /usr/local/bin/echo-start.sh
RUN chmod +x /usr/local/bin/echo-start.sh

EXPOSE 6001

CMD ["echo-start.sh"]