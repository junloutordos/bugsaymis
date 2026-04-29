FROM nginx:stable-alpine

RUN rm /etc/nginx/conf.d/default.conf

COPY conf.d/default.conf /etc/nginx/conf.d/default.conf
COPY ssl/cloudflare.crt /etc/nginx/ssl/cloudflare.crt
COPY ssl/cloudflare.key /etc/nginx/ssl/cloudflare.key

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]
