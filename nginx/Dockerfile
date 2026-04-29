FROM nginx:stable-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY nginx/conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY nginx/ssl/cloudflare.crt /etc/nginx/ssl/cloudflare.crt
COPY nginx/ssl/cloudflare.key /etc/nginx/ssl/cloudflare.key

COPY public /var/www/public

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
